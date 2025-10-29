<?php

declare(strict_types=1);

namespace X402\Tests\Middleware;

use PHPUnit\Framework\TestCase;
use X402\Encoding\Encoder;
use X402\Exceptions\PaymentRequiredException;
use X402\Exceptions\ValidationException;
use X402\Middleware\PaymentHandler;
use X402\Types\EIP3009Authorization;
use X402\Types\ExactPaymentPayload;
use X402\Types\ExactSvmPayload;
use X402\Types\PaymentPayload;
use X402\Types\PaymentRequirements;

class PaymentHandlerTest extends TestCase
{
    private function createRequirements(): PaymentRequirements
    {
        return new PaymentRequirements(
            scheme: 'exact',
            network: 'base-sepolia',
            maxAmountRequired: '10000',
            resource: 'https://api.example.com/data',
            description: 'Premium data',
            mimeType: 'application/json',
            payTo: '0x209693Bc6afc0C5328bA36FaF03C514EF312287C',
            maxTimeoutSeconds: 60,
            asset: '0x036CbD53842c5426634e7929541eC2318f3dCF7e',
            extra: [
                'name' => 'TestDomain',
                'version' => '1'
            ]
        );
    }

    private function createPayload(array $overrides = []): PaymentPayload
    {
        $now = time();
        $defaultValidAfter = (string)($now - 60);
        $defaultValidBefore = (string)($now + 600);

        $authorization = new EIP3009Authorization(
            from: $overrides['from'] ?? '0x857b06519E91e3A54538791bDbb0E22373e36b66',
            to: $overrides['to'] ?? '0x209693Bc6afc0C5328bA36FaF03C514EF312287C',
            value: $overrides['value'] ?? '10000',
            validAfter: $overrides['validAfter'] ?? $defaultValidAfter,
            validBefore: $overrides['validBefore'] ?? $defaultValidBefore,
            nonce: $overrides['nonce'] ?? '0xf3746613c2d920b5fdabc0856f2aeb2d4f88ee6037b8cc5d04a71a4462f13480'
        );

        $payload = new ExactPaymentPayload(
            signature: $overrides['signature'] ?? '0x' . str_repeat('a', 130),
            authorization: $authorization
        );

        return new PaymentPayload(
            x402Version: $overrides['x402Version'] ?? 1,
            scheme: 'exact',
            network: 'base-sepolia',
            payload: $payload
        );
    }

    public function testCreatePaymentRequirementsRejectsUnsupportedScheme(): void
    {
        $handler = new PaymentHandler();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Unsupported payment scheme: subscription');

        $handler->createPaymentRequirements(
            payTo: '0x209693Bc6afc0C5328bA36FaF03C514EF312287C',
            amount: '10000',
            resource: 'https://api.example.com/data',
            description: 'Premium data',
            asset: '0x036CbD53842c5426634e7929541eC2318f3dCF7e',
            network: 'base-sepolia',
            scheme: 'subscription'
        );
    }

    public function testCreatePaymentRequirementsRejectsUnsupportedNetwork(): void
    {
        $handler = new PaymentHandler();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid network. Supported networks: ');

        $handler->createPaymentRequirements(
            payTo: '0x209693Bc6afc0C5328bA36FaF03C514EF312287C',
            amount: '10000',
            resource: 'https://api.example.com/data',
            description: 'Premium data',
            asset: '0x036CbD53842c5426634e7929541eC2318f3dCF7e',
            network: 'unsupported-network'
        );
    }

    public function testVerifyPaymentFailsForUnsupportedVersion(): void
    {
        $handler = new PaymentHandler();
        $requirements = $this->createRequirements();
        $payload = $this->createPayload(['x402Version' => 2]);
        $header = Encoder::encodePaymentHeader($payload);

        $this->expectException(PaymentRequiredException::class);
        $this->expectExceptionMessage('Unsupported x402 version');

        $result = $handler->verifyPayment($header, $requirements);
        $this->assertSame('invalid_version', $result->invalidReason);
    }

    public function testVerifyPaymentFailsWhenRecipientDiffers(): void
    {
        $handler = new PaymentHandler();
        $requirements = $this->createRequirements();
        $payload = $this->createPayload([
            'to' => '0x0000000000000000000000000000000000000001',
        ]);
        $header = Encoder::encodePaymentHeader($payload);

        $this->expectException(PaymentRequiredException::class);
        $this->expectExceptionMessage('Payment recipient mismatch');

        $handler->verifyPayment($header, $requirements);
    }

    public function testVerifyPaymentSucceedsWhenPayloadMatchesRequirements(): void
    {
        $handler = new PaymentHandler();
        $requirements = $this->createRequirements();
        $payload = $this->createPayload();
        $header = Encoder::encodePaymentHeader($payload);

        $result = $handler->verifyPayment($header, $requirements);

        $this->assertSame(1, $result->x402Version);
        $this->assertSame('exact', $result->scheme);
        $this->assertInstanceOf(ExactPaymentPayload::class, $result->payload);
    }

    public function testExtractPaymentHeaderSupportsServerArray(): void
    {
        $handler = new PaymentHandler();
        $expected = 'header-value';

        $header = $handler->extractPaymentHeader([
            'HTTP_X_PAYMENT' => $expected,
        ]);

        $this->assertSame($expected, $header);
    }

    // ========== Solana (SVM) Tests ==========

    private function createSolanaRequirements(): PaymentRequirements
    {
        return new PaymentRequirements(
            scheme: 'exact',
            network: 'solana-devnet',
            maxAmountRequired: '1000000',
            resource: 'https://api.example.com/solana-data',
            description: 'Solana Premium data',
            mimeType: 'application/json',
            payTo: '9B5XszUGdMaxCZ7uSQhPzdks5ZQSmWxrmzCSvtJ6Ns6g',
            maxTimeoutSeconds: 60,
            asset: 'EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v' // USDC on Solana
        );
    }

    private function createSolanaPayload(array $overrides = []): PaymentPayload
    {
        // Mock base64-encoded Solana transaction
        $mockTransaction = base64_encode(str_repeat('A', 120));
        
        $payload = new ExactSvmPayload(
            transaction: $overrides['transaction'] ?? $mockTransaction
        );

        return new PaymentPayload(
            x402Version: $overrides['x402Version'] ?? 1,
            scheme: 'exact',
            network: 'solana-devnet',
            payload: $payload
        );
    }

    public function testVerifyPaymentFailsForEmptySolanaTransaction(): void
    {
        $handler = new PaymentHandler();
        $requirements = $this->createSolanaRequirements();
        $payload = $this->createSolanaPayload(['transaction' => '']);
        $header = Encoder::encodePaymentHeader($payload);

        $this->expectException(PaymentRequiredException::class);
        $this->expectExceptionMessage('Solana transaction is empty');

        $handler->verifyPayment($header, $requirements);
    }

    public function testVerifyPaymentFailsForInvalidBase64SolanaTransaction(): void
    {
        $handler = new PaymentHandler();
        $requirements = $this->createSolanaRequirements();
        $payload = $this->createSolanaPayload(['transaction' => 'not-valid-base64!!!']);
        $header = Encoder::encodePaymentHeader($payload);

        $this->expectException(PaymentRequiredException::class);
        $this->expectExceptionMessage('Invalid base64-encoded Solana transaction');

        $handler->verifyPayment($header, $requirements);
    }

    public function testVerifyPaymentFailsForSolanaWithoutFacilitator(): void
    {
        $handler = new PaymentHandler();
        $requirements = $this->createSolanaRequirements();
        $payload = $this->createSolanaPayload();
        $header = Encoder::encodePaymentHeader($payload);

        $this->expectException(PaymentRequiredException::class);
        $this->expectExceptionMessage('Facilitator required for Solana transaction verification');

        $handler->verifyPayment($header, $requirements);
    }

    public function testVerifyPaymentFailsForEVMWithoutEIP712DomainName(): void
    {
        $handler = new PaymentHandler();
        $requirements = new PaymentRequirements(
            scheme: 'exact',
            network: 'base-sepolia',
            maxAmountRequired: '10000',
            resource: 'https://api.example.com/data',
            description: 'Premium data',
            mimeType: 'application/json',
            payTo: '0x209693Bc6afc0C5328bA36FaF03C514EF312287C',
            maxTimeoutSeconds: 60,
            asset: '0x036CbD53842c5426634e7929541eC2318f3dCF7e',
            extra: ['version' => '1'] // missing 'name'
        );
        $payload = $this->createPayload();
        $header = Encoder::encodePaymentHeader($payload);

        $this->expectException(PaymentRequiredException::class);
        $this->expectExceptionMessage('EIP-712 domain name required');

        $handler->verifyPayment($header, $requirements);
    }

    public function testVerifyPaymentFailsForEVMWithoutEIP712DomainVersion(): void
    {
        $handler = new PaymentHandler();
        $requirements = new PaymentRequirements(
            scheme: 'exact',
            network: 'base-sepolia',
            maxAmountRequired: '10000',
            resource: 'https://api.example.com/data',
            description: 'Premium data',
            mimeType: 'application/json',
            payTo: '0x209693Bc6afc0C5328bA36FaF03C514EF312287C',
            maxTimeoutSeconds: 60,
            asset: '0x036CbD53842c5426634e7929541eC2318f3dCF7e',
            extra: ['name' => 'TestDomain'] // missing 'version'
        );
        $payload = $this->createPayload();
        $header = Encoder::encodePaymentHeader($payload);

        $this->expectException(PaymentRequiredException::class);
        $this->expectExceptionMessage('EIP-712 domain version required');

        $handler->verifyPayment($header, $requirements);
    }
}
