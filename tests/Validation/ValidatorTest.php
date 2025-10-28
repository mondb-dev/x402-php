<?php

declare(strict_types=1);

namespace X402\Tests\Validation;

use PHPUnit\Framework\TestCase;
use X402\Exceptions\ValidationException;
use X402\Validation\Validator;

class ValidatorTest extends TestCase
{
    public function testValidEthereumAddress(): void
    {
        $this->assertTrue(Validator::isValidEthereumAddress('0x1234567890123456789012345678901234567890'));
        $this->assertTrue(Validator::isValidEthereumAddress('0xABCDEF1234567890123456789012345678901234'));
    }

    public function testInvalidEthereumAddress(): void
    {
        $this->assertFalse(Validator::isValidEthereumAddress('1234567890123456789012345678901234567890')); // missing 0x
        $this->assertFalse(Validator::isValidEthereumAddress('0x12345')); // too short
        $this->assertFalse(Validator::isValidEthereumAddress('0x123456789012345678901234567890123456789G')); // invalid char
    }

    public function testValidUintString(): void
    {
        $this->assertTrue(Validator::isValidUintString('0'));
        $this->assertTrue(Validator::isValidUintString('123'));
        $this->assertTrue(Validator::isValidUintString('1000000'));
    }

    public function testInvalidUintString(): void
    {
        $this->assertFalse(Validator::isValidUintString('-123')); // negative
        $this->assertFalse(Validator::isValidUintString('12.34')); // decimal
        $this->assertFalse(Validator::isValidUintString('abc')); // non-numeric
        $this->assertFalse(Validator::isValidUintString('00123')); // leading zeros
    }

    public function testValidatePaymentRequirementsValid(): void
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

        $this->expectNotToPerformAssertions();
        Validator::validatePaymentRequirements($data);
    }

    public function testValidatePaymentRequirementsMissingField(): void
    {
        $data = [
            'scheme' => 'exact',
            'network' => 'base-sepolia',
            // missing maxAmountRequired
        ];

        $this->expectException(ValidationException::class);
        Validator::validatePaymentRequirements($data);
    }

    public function testValidatePaymentRequirementsInvalidAddress(): void
    {
        $data = [
            'scheme' => 'exact',
            'network' => 'base-sepolia',
            'maxAmountRequired' => '1000000',
            'resource' => 'https://example.com/api/data',
            'description' => 'Test resource',
            'mimeType' => 'application/json',
            'payTo' => 'invalid-address', // invalid
            'maxTimeoutSeconds' => 300,
            'asset' => '0x0987654321098765432109876543210987654321',
        ];

        $this->expectException(ValidationException::class);
        Validator::validatePaymentRequirements($data);
    }

    public function testSanitizeString(): void
    {
        $input = '<script>alert("xss")</script>';
        $sanitized = Validator::sanitizeString($input);
        
        $this->assertStringNotContainsString('<script>', $sanitized);
        $this->assertStringContainsString('&lt;script&gt;', $sanitized);
    }

    public function testSanitizeUrl(): void
    {
        $validUrl = 'https://example.com/api/data';
        $sanitized = Validator::sanitizeUrl($validUrl);
        
        $this->assertEquals($validUrl, $sanitized);
    }

    public function testSanitizeUrlInvalid(): void
    {
        $this->expectException(ValidationException::class);
        Validator::sanitizeUrl('not-a-valid-url');
    }
}
