<?php

/**
 * Laravel Integration Example
 * 
 * This example shows how to integrate x402-php with Laravel framework.
 */

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use X402\Config\X402Config;
use X402\Exceptions\X402Exception;
use X402\Middleware\PaymentHandler;
use X402\Nonce\RedisNonceTracker;
use X402\RateLimit\RedisRateLimiter;
use X402\Types\PaymentRequiredResponse;
use X402\Types\PaymentRequirements;

/**
 * Laravel middleware for x402 payment verification.
 * 
 * Installation:
 * 1. Copy this file to app/Http/Middleware/X402PaymentMiddleware.php
 * 2. Register in app/Http/Kernel.php:
 *    protected $routeMiddleware = [
 *        'x402' => \App\Http\Middleware\X402PaymentMiddleware::class,
 *    ];
 * 3. Use in routes:
 *    Route::get('/api/premium', [ApiController::class, 'premium'])
 *         ->middleware('x402:1000000'); // 1 USDC
 */
class X402PaymentMiddleware
{
    private PaymentHandler $paymentHandler;
    
    public function __construct()
    {
        // Load configuration from Laravel config
        $config = X402Config::fromEnvironment();
        $facilitator = $config->createFacilitatorClient();
        
        // Set up Redis for nonce tracking and rate limiting
        $redis = app('redis')->connection()->client();
        
        $this->paymentHandler = new PaymentHandler(
            facilitator: $facilitator,
            autoSettle: config('x402.auto_settle', true),
            validBeforeBufferSeconds: config('x402.buffer_seconds', 6),
            nonceTracker: new RedisNonceTracker($redis, 'x402:nonces:'),
            rateLimiter: new RedisRateLimiter($redis, 100, 60, 'x402:ratelimit:'),
            logger: Log::channel('x402')
        );
    }
    
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @param string $amount Amount required in smallest unit (e.g., '1000000' for 1 USDC)
     * @return Response
     */
    public function handle(Request $request, Closure $next, string $amount = '1000000'): Response
    {
        // Build payment requirements
        $requirements = new PaymentRequirements(
            id: 'req-' . uniqid(),
            scheme: 'exact',
            network: config('x402.network', 'base-mainnet'),
            maxAmountRequired: $amount,
            resource: $request->fullUrl(),
            description: config('x402.description', 'API access'),
            mimeType: 'application/json',
            payTo: config('x402.pay_to_address'),
            maxTimeoutSeconds: config('x402.timeout', 60),
            asset: config('x402.asset_address') // USDC contract address
        );
        
        try {
            // Get payment header
            $paymentHeader = $request->header('X-Payment', '');
            
            if (empty($paymentHeader)) {
                return $this->paymentRequired($requirements);
            }
            
            // Verify payment
            $result = $this->paymentHandler->verifyPayment($paymentHeader, $requirements);
            
            // Add payment info to request for use in controller
            $request->merge([
                'x402_payment' => $result,
                'x402_verified' => true,
                'x402_payer' => $result['from'] ?? null,
            ]);
            
            Log::info('Payment verified', [
                'payer' => $result['from'] ?? 'unknown',
                'amount' => $amount,
                'route' => $request->path(),
            ]);
            
            // Continue to controller
            return $next($request);
            
        } catch (X402Exception $e) {
            Log::warning('Payment verification failed', [
                'error' => $e->getMessage(),
                'route' => $request->path(),
                'ip' => $request->ip(),
            ]);
            
            return $this->paymentRequired($requirements, $e->getMessage());
        }
    }
    
    /**
     * Build a 402 Payment Required response.
     */
    private function paymentRequired(
        PaymentRequirements $requirements,
        string $error = ''
    ): Response {
        $paymentResponse = new PaymentRequiredResponse(
            x402Version: 1,
            accepts: [$requirements],
            error: $error
        );
        
        return response()
            ->json($paymentResponse->toArray(), 402)
            ->withHeaders($paymentResponse->getHeaders());
    }
}

// ============================================================================
// Configuration File: config/x402.php
// ============================================================================

/*
return [
    // Facilitator configuration
    'facilitator_url' => env('X402_FACILITATOR_URL', 'https://facilitator.coinbase.com/api/v1'),
    'facilitator_api_key' => env('X402_FACILITATOR_API_KEY'),
    
    // Payment settings
    'pay_to_address' => env('X402_PAY_TO_ADDRESS'),
    'asset_address' => env('X402_ASSET_ADDRESS', '0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913'), // Base USDC
    'network' => env('X402_NETWORK', 'base-mainnet'),
    
    // Description shown to payers
    'description' => env('X402_DESCRIPTION', 'API access'),
    
    // Timeouts
    'timeout' => (int) env('X402_TIMEOUT', 60),
    'buffer_seconds' => (int) env('X402_BUFFER_SECONDS', 6),
    
    // Auto-settle payments
    'auto_settle' => (bool) env('X402_AUTO_SETTLE', true),
];
*/

// ============================================================================
// Logging Configuration: config/logging.php
// ============================================================================

/*
'channels' => [
    'x402' => [
        'driver' => 'daily',
        'path' => storage_path('logs/x402.log'),
        'level' => env('LOG_LEVEL', 'info'),
        'days' => 30,
    ],
],
*/

// ============================================================================
// Controller Example
// ============================================================================

/*
namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ApiController extends Controller
{
    public function premium(Request $request)
    {
        // Payment is already verified by middleware
        $payer = $request->input('x402_payer');
        
        return response()->json([
            'message' => 'Welcome to premium API',
            'payer' => $payer,
            'data' => [
                'premium' => 'content',
                'timestamp' => now()->toIso8601String(),
            ]
        ]);
    }
}
*/

// ============================================================================
// Route Example: routes/api.php
// ============================================================================

/*
use App\Http\Controllers\ApiController;

// Free endpoint
Route::get('/api/free', [ApiController::class, 'free']);

// Paid endpoints with different pricing
Route::get('/api/premium', [ApiController::class, 'premium'])
    ->middleware('x402:1000000'); // 1 USDC

Route::get('/api/ultra', [ApiController::class, 'ultra'])
    ->middleware('x402:5000000'); // 5 USDC

// Dynamic pricing based on request parameters
Route::get('/api/data/{records}', function (Request $request, int $records) {
    // Calculate price: 0.001 USDC per record
    $pricePerRecord = 1000; // 0.001 USDC with 6 decimals
    $totalPrice = $records * $pricePerRecord;
    
    return response()->json([
        'records' => $records,
        'price_usdc' => $totalPrice / 1000000,
    ]);
})->middleware('x402:' . ($records * 1000));
*/
