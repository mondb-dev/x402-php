<?php

/**
 * Test script to verify HTTP 402 status code is correctly sent
 * 
 * This tests that the WWW-Authenticate header doesn't cause PHP to override
 * the status code from 402 to 401.
 * 
 * Run this script and check the HTTP status:
 *   php -S localhost:8000 examples/test-402-status.php
 *   curl -I http://localhost:8000
 * 
 * Expected output:
 *   HTTP/1.1 402 Payment Required
 *   WWW-Authenticate: X-Payment
 *   Content-Type: application/json
 * 
 * If you see "HTTP/1.1 401 Unauthorized" instead, the fix is not working.
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use X402\Facilitator\FacilitatorClient;
use X402\Middleware\PaymentHandler;

// Create a simple payment handler
$facilitator = FacilitatorClient::payai();
$handler = new PaymentHandler($facilitator);

// Create payment requirements
$requirements = $handler->createPaymentRequirements(
    payTo: '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb',
    amount: '1000000',
    resource: 'http://localhost:8000/test',
    description: 'Test resource',
    asset: '0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913',
    network: 'base-mainnet',
    extra: ['name' => 'USD Coin', 'version' => '2']
);

// Create 402 response
$paymentRequired = $handler->createPaymentRequiredResponse(
    requirements: $requirements,
    error: 'Payment required for this test resource'
);

// Method 1: Use send() method (RECOMMENDED)
echo "=== Test 1: Using send() method (RECOMMENDED) ===\n";
echo "This should return HTTP 402 Payment Required\n\n";

// Capture the output to verify
ob_start();
$paymentRequired->send(
    headerSender: function(string $name, string $value) {
        echo "Header: {$name}: {$value}\n";
        header("{$name}: {$value}");
    },
    statusSender: function(int $code) {
        echo "Status Code: {$code}\n";
        http_response_code($code);
    },
    bodySender: function(string $body) {
        echo "Body: " . substr($body, 0, 100) . "...\n";
    }
);
$output = ob_get_clean();

echo $output;
echo "\n";

// Verify status code
$actualStatusCode = http_response_code();
if ($actualStatusCode === 402) {
    echo "✅ SUCCESS: Status code is 402 as expected\n";
} else {
    echo "❌ FAILURE: Status code is {$actualStatusCode}, expected 402\n";
    echo "This indicates PHP is overriding to 401 due to WWW-Authenticate header\n";
}

echo "\n";
echo "=== Test 2: Manual method (headers first, then status) ===\n";

// Reset status code
http_response_code(200);

// Set headers first
header('WWW-Authenticate: X-Payment', true);
header('Content-Type: application/json', true);
header('X-Payment-Accept: exact', true);

// Then set status code
http_response_code(402);

$actualStatusCode = http_response_code();
if ($actualStatusCode === 402) {
    echo "✅ SUCCESS: Manual method also works (status code is 402)\n";
} else {
    echo "❌ FAILURE: Status code is {$actualStatusCode}, expected 402\n";
}

echo "\n";
echo "=== Test 3: WRONG method (status first, then headers) ===\n";

// Reset
http_response_code(200);
header_remove();

// Wrong order: status code first
http_response_code(402);

// Then headers (this might cause PHP to override to 401)
header('WWW-Authenticate: X-Payment', true);
header('Content-Type: application/json', true);

$actualStatusCode = http_response_code();
if ($actualStatusCode === 402) {
    echo "⚠️  UNEXPECTED: Status code is still 402 (PHP version may not have this bug)\n";
} else {
    echo "❌ EXPECTED FAILURE: Status code is {$actualStatusCode} (PHP overrode to 401)\n";
    echo "This demonstrates the bug we're protecting against\n";
}

echo "\n";
echo "=== Summary ===\n";
echo "Always use PaymentRequiredResponse::send() method or set headers before status code.\n";
echo "This ensures compatibility across all PHP versions.\n";
