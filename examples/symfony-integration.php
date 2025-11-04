<?php

/**
 * Symfony Integration Example
 * 
 * This example shows how to integrate x402-php with Symfony framework.
 */

declare(strict_types=1);

namespace App\EventSubscriber;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use X402\Config\X402Config;
use X402\Exceptions\X402Exception;
use X402\Middleware\PaymentHandler;
use X402\Nonce\RedisNonceTracker;
use X402\RateLimit\RedisRateLimiter;
use X402\Types\PaymentRequiredResponse;
use X402\Types\PaymentRequirements;

/**
 * Symfony Event Subscriber for x402 payment verification.
 * 
 * Installation:
 * 1. Copy this file to src/EventSubscriber/X402PaymentSubscriber.php
 * 2. Configure services in config/services.yaml (see below)
 * 3. Add route attribute #[X402Payment(amount: 1000000)]
 */
class X402PaymentSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly PaymentHandler $paymentHandler,
        private readonly LoggerInterface $logger,
        private readonly string $payToAddress,
        private readonly string $assetAddress,
        private readonly string $network
    ) {
    }
    
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 10],
        ];
    }
    
    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        
        // Check if route requires x402 payment
        $x402Config = $request->attributes->get('_x402');
        
        if ($x402Config === null) {
            return; // Route doesn't require payment
        }
        
        $amount = $x402Config['amount'] ?? '1000000';
        
        // Build payment requirements
        $requirements = new PaymentRequirements(
            id: 'req-' . uniqid(),
            scheme: 'exact',
            network: $this->network,
            maxAmountRequired: (string)$amount,
            resource: $request->getUri(),
            description: $x402Config['description'] ?? 'API access',
            mimeType: 'application/json',
            payTo: $this->payToAddress,
            maxTimeoutSeconds: 60,
            asset: $this->assetAddress
        );
        
        try {
            // Get payment header
            $paymentHeader = $request->headers->get('X-Payment', '');
            
            if ($paymentHeader === '') {
                $event->setResponse($this->paymentRequiredResponse($requirements));
                return;
            }
            
            // Verify payment
            $result = $this->paymentHandler->verifyPayment($paymentHeader, $requirements);
            
            // Add payment info to request attributes
            $request->attributes->set('x402_payment', $result);
            $request->attributes->set('x402_verified', true);
            $request->attributes->set('x402_payer', $result['from'] ?? null);
            
            $this->logger->info('Payment verified', [
                'payer' => $result['from'] ?? 'unknown',
                'amount' => $amount,
                'route' => $request->getPathInfo(),
            ]);
            
        } catch (X402Exception $e) {
            $this->logger->warning('Payment verification failed', [
                'error' => $e->getMessage(),
                'route' => $request->getPathInfo(),
                'ip' => $request->getClientIp(),
            ]);
            
            $event->setResponse($this->paymentRequiredResponse($requirements, $e->getMessage()));
        }
    }
    
    private function paymentRequiredResponse(
        PaymentRequirements $requirements,
        string $error = ''
    ): Response {
        $paymentResponse = new PaymentRequiredResponse(
            x402Version: 1,
            accepts: [$requirements],
            error: $error
        );
        
        return new JsonResponse(
            $paymentResponse->toArray(),
            Response::HTTP_PAYMENT_REQUIRED,
            $paymentResponse->getHeaders()
        );
    }
}

// ============================================================================
// Service Configuration: config/services.yaml
// ============================================================================

/*
services:
    # Redis client
    Redis:
        class: Redis
        calls:
            - connect: ['%env(REDIS_HOST)%', '%env(int:REDIS_PORT)%']
            - auth: ['%env(REDIS_PASSWORD)%']
    
    # Nonce tracker
    X402\Nonce\RedisNonceTracker:
        arguments:
            $redis: '@Redis'
            $keyPrefix: 'x402:nonces:'
    
    # Rate limiter
    X402\RateLimit\RedisRateLimiter:
        arguments:
            $redis: '@Redis'
            $maxRequests: 100
            $windowSeconds: 60
            $keyPrefix: 'x402:ratelimit:'
    
    # Facilitator client
    X402\Facilitator\FacilitatorClient:
        factory: ['X402\Config\X402Config', 'fromEnvironment']
        calls:
            - createFacilitatorClient: []
    
    # Payment handler
    X402\Middleware\PaymentHandler:
        arguments:
            $facilitator: '@X402\Facilitator\FacilitatorClient'
            $autoSettle: '%env(bool:X402_AUTO_SETTLE)%'
            $validBeforeBufferSeconds: '%env(int:X402_BUFFER_SECONDS)%'
            $nonceTracker: '@X402\Nonce\RedisNonceTracker'
            $rateLimiter: '@X402\RateLimit\RedisRateLimiter'
            $logger: '@logger'
    
    # Event subscriber
    App\EventSubscriber\X402PaymentSubscriber:
        arguments:
            $paymentHandler: '@X402\Middleware\PaymentHandler'
            $logger: '@logger'
            $payToAddress: '%env(X402_PAY_TO_ADDRESS)%'
            $assetAddress: '%env(X402_ASSET_ADDRESS)%'
            $network: '%env(X402_NETWORK)%'
        tags:
            - { name: kernel.event_subscriber }
*/

// ============================================================================
// Environment Configuration: .env
// ============================================================================

/*
# x402 Configuration
X402_FACILITATOR_URL=https://facilitator.coinbase.com/api/v1
X402_FACILITATOR_API_KEY=your-api-key-here
X402_PAY_TO_ADDRESS=0xYourAddress
X402_ASSET_ADDRESS=0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913
X402_NETWORK=base-mainnet
X402_AUTO_SETTLE=true
X402_BUFFER_SECONDS=6

# Redis Configuration
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=your-redis-password
*/

// ============================================================================
// Route Attribute
// ============================================================================

/*
namespace App\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class X402Payment
{
    public function __construct(
        public readonly int $amount = 1000000,
        public readonly string $description = 'API access'
    ) {
    }
}
*/

// ============================================================================
// Controller Example
// ============================================================================

/*
namespace App\Controller;

use App\Attribute\X402Payment;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ApiController extends AbstractController
{
    #[Route('/api/free', methods: ['GET'])]
    public function free(): JsonResponse
    {
        return $this->json([
            'message' => 'This endpoint is free',
            'data' => ['some' => 'data'],
        ]);
    }
    
    #[Route('/api/premium', methods: ['GET'])]
    #[X402Payment(amount: 1000000, description: 'Premium API access')]
    public function premium(Request $request): JsonResponse
    {
        $payer = $request->attributes->get('x402_payer');
        
        return $this->json([
            'message' => 'Welcome to premium API',
            'payer' => $payer,
            'data' => ['premium' => 'content'],
        ]);
    }
    
    #[Route('/api/ultra/{records}', methods: ['GET'])]
    public function ultra(Request $request, int $records): JsonResponse
    {
        // Dynamic pricing based on records
        $pricePerRecord = 1000; // 0.001 USDC
        $totalPrice = $records * $pricePerRecord;
        
        // Set payment requirement dynamically
        $request->attributes->set('_x402', [
            'amount' => $totalPrice,
            'description' => "Data access for {$records} records"
        ]);
        
        $payer = $request->attributes->get('x402_payer');
        
        return $this->json([
            'records' => $records,
            'price_usdc' => $totalPrice / 1000000,
            'payer' => $payer,
            'data' => range(1, $records),
        ]);
    }
}
*/

// ============================================================================
// Testing with curl
// ============================================================================

/*
# Test free endpoint
curl http://localhost:8000/api/free

# Test premium endpoint (will get 402)
curl -v http://localhost:8000/api/premium

# Test with payment
curl -H "X-Payment: <base64-encoded-payment>" \
     http://localhost:8000/api/premium
*/
