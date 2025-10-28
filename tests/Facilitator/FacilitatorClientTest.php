<?php

declare(strict_types=1);

namespace X402\Tests\Facilitator;

use PHPUnit\Framework\TestCase;
use X402\Facilitator\FacilitatorClient;
use X402\Exceptions\FacilitatorException;
use X402\Types\PaymentRequirements;

class FacilitatorClientTest extends TestCase
{
    private function createMockRequirements(): PaymentRequirements
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

    public function testConstructorAcceptsBaseUrl(): void
    {
        $baseUrl = 'https://facilitator.example.com';
        $client = new FacilitatorClient($baseUrl);

        $this->assertInstanceOf(FacilitatorClient::class, $client);
    }

    public function testConstructorThrowsForInvalidUrl(): void
    {
        $this->expectException(FacilitatorException::class);
        $this->expectExceptionMessage('Invalid facilitator base URL');

        new FacilitatorClient('not-a-valid-url');
    }

    public function testConstructorThrowsForNonHttpsUrl(): void
    {
        $this->expectException(FacilitatorException::class);
        $this->expectExceptionMessage('Facilitator URL must use HTTPS');

        new FacilitatorClient('http://insecure.example.com');
    }

    public function testGetSupportedReturnsArray(): void
    {
        // Skip this test if running without actual facilitator
        // In real implementation, you would mock HTTP responses
        $this->markTestSkipped('Requires HTTP client mocking or actual facilitator');

        $client = new FacilitatorClient('https://facilitator.example.com');
        $supported = $client->getSupported();

        $this->assertIsArray($supported);
    }

    public function testVerifyRequiresValidPaymentHeader(): void
    {
        $client = new FacilitatorClient('https://facilitator.example.com');
        $requirements = $this->createMockRequirements();

        $this->expectException(FacilitatorException::class);
        $this->expectExceptionMessage('Invalid payment header');

        $client->verify('', $requirements);
    }

    public function testVerifyRequiresValidRequirements(): void
    {
        $client = new FacilitatorClient('https://facilitator.example.com');

        $this->expectException(\TypeError::class);

        // @phpstan-ignore-next-line - intentionally passing null to test type safety
        $client->verify('valid-base64-header', null);
    }

    public function testSettleRequiresValidPaymentPayload(): void
    {
        $client = new FacilitatorClient('https://facilitator.example.com');
        $requirements = $this->createMockRequirements();

        $this->expectException(FacilitatorException::class);

        // @phpstan-ignore-next-line - intentionally passing invalid data
        $client->settle('', $requirements);
    }

    public function testTimeoutConfigurationIsApplied(): void
    {
        $client = new FacilitatorClient('https://facilitator.example.com', 30);

        $this->assertInstanceOf(FacilitatorClient::class, $client);
        // Timeout would be tested by checking the actual HTTP request
        // This is a structural test to ensure the parameter is accepted
    }

    public function testApiKeyConfigurationIsApplied(): void
    {
        $client = new FacilitatorClient(
            'https://facilitator.example.com',
            60,
            'test-api-key'
        );

        $this->assertInstanceOf(FacilitatorClient::class, $client);
        // API key would be tested by checking the actual HTTP request headers
        // This is a structural test to ensure the parameter is accepted
    }

    /**
     * Test that facilitator client handles network errors gracefully
     */
    public function testHandlesNetworkErrorsGracefully(): void
    {
        // This test would require mocking the HTTP client
        // For now, we'll mark it as a placeholder
        $this->markTestSkipped('Requires HTTP client mocking');

        $client = new FacilitatorClient('https://unreachable.example.com');
        $requirements = $this->createMockRequirements();

        $this->expectException(FacilitatorException::class);
        $this->expectExceptionMessageMatches('/network error|connection failed/i');

        $client->verify('some-header', $requirements);
    }

    /**
     * Test that facilitator client handles invalid JSON responses
     */
    public function testHandlesInvalidJsonResponse(): void
    {
        // This test would require mocking the HTTP client
        $this->markTestSkipped('Requires HTTP client mocking');
    }

    /**
     * Test that facilitator client respects timeout settings
     */
    public function testRespectsTimeoutSettings(): void
    {
        // This test would require mocking slow HTTP responses
        $this->markTestSkipped('Requires HTTP client mocking');
    }
}
