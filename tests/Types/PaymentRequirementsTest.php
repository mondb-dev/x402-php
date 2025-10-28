<?php

declare(strict_types=1);

namespace X402\Tests\Types;

use PHPUnit\Framework\TestCase;
use X402\Types\PaymentRequirements;

class PaymentRequirementsTest extends TestCase
{
    public function testCreatePaymentRequirements(): void
    {
        $requirements = new PaymentRequirements(
            scheme: 'exact',
            network: 'base-sepolia',
            maxAmountRequired: '1000000',
            resource: 'https://example.com/api/data',
            description: 'Test resource',
            mimeType: 'application/json',
            payTo: '0x1234567890123456789012345678901234567890',
            maxTimeoutSeconds: 300,
            asset: '0x0987654321098765432109876543210987654321'
        );

        $this->assertEquals('exact', $requirements->scheme);
        $this->assertEquals('base-sepolia', $requirements->network);
        $this->assertEquals('1000000', $requirements->maxAmountRequired);
        $this->assertEquals('https://example.com/api/data', $requirements->resource);
        $this->assertEquals('Test resource', $requirements->description);
        $this->assertEquals('application/json', $requirements->mimeType);
        $this->assertEquals('0x1234567890123456789012345678901234567890', $requirements->payTo);
        $this->assertEquals(300, $requirements->maxTimeoutSeconds);
        $this->assertEquals('0x0987654321098765432109876543210987654321', $requirements->asset);
    }

    public function testToArray(): void
    {
        $requirements = new PaymentRequirements(
            scheme: 'exact',
            network: 'base-sepolia',
            maxAmountRequired: '1000000',
            resource: 'https://example.com/api/data',
            description: 'Test resource',
            mimeType: 'application/json',
            payTo: '0x1234567890123456789012345678901234567890',
            maxTimeoutSeconds: 300,
            asset: '0x0987654321098765432109876543210987654321'
        );

        $array = $requirements->toArray();

        $this->assertIsArray($array);
        $this->assertEquals('exact', $array['scheme']);
        $this->assertEquals('base-sepolia', $array['network']);
        $this->assertEquals('1000000', $array['maxAmountRequired']);
    }

    public function testFromArray(): void
    {
        $data = [
            'scheme' => 'exact',
            'network' => 'base-sepolia',
            'maxAmountRequired' => '1000000',
            'resource' => 'https://example.com/api/data',
            'description' => 'Test resource',
            'mimeType' => 'application/json',
            'payTo' => '0x1234567890123456789012345678901234567890',
            'maxTimeoutSeconds' => 300,
            'asset' => '0x0987654321098765432109876543210987654321',
        ];

        $requirements = PaymentRequirements::fromArray($data);

        $this->assertEquals('exact', $requirements->scheme);
        $this->assertEquals('base-sepolia', $requirements->network);
        $this->assertEquals('1000000', $requirements->maxAmountRequired);
    }

    public function testJsonSerializable(): void
    {
        $requirements = new PaymentRequirements(
            scheme: 'exact',
            network: 'base-sepolia',
            maxAmountRequired: '1000000',
            resource: 'https://example.com/api/data',
            description: 'Test resource',
            mimeType: 'application/json',
            payTo: '0x1234567890123456789012345678901234567890',
            maxTimeoutSeconds: 300,
            asset: '0x0987654321098765432109876543210987654321'
        );

        $json = json_encode($requirements);
        $this->assertIsString($json);
        
        $decoded = json_decode($json, true);
        $this->assertEquals('exact', $decoded['scheme']);
        $this->assertEquals('base-sepolia', $decoded['network']);
    }
}
