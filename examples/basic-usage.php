<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use X402\Facilitator\FacilitatorClient;
use X402\Middleware\PaymentHandler;

// Example: Basic usage of x402-php

// 1. Initialize facilitator client (optional, but recommended for production)
$facilitatorUrl = 'https://facilitator.x402.org'; // Replace with your facilitator URL
$facilitator = new FacilitatorClient($facilitatorUrl);

// 2. Create payment handler
$handler = new PaymentHandler(
    facilitator: $facilitator,
    autoSettle: true // Automatically settle payments after verification
);

// 3. Define payment requirements for your resource
$payTo = '0x1234567890123456789012345678901234567890'; // Your receiving address
$amount = '1000000'; // Amount in atomic units (1 USDC = 1000000 with 6 decimals)
$asset = '0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913'; // USDC on Base
$network = 'base-mainnet';

$requirements = $handler->createPaymentRequirements(
    payTo: $payTo,
    amount: $amount,
    resource: 'https://your-api.com/premium-data',
    description: 'Premium API access',
    asset: $asset,
    network: $network,
    scheme: 'exact',
    timeout: 300,
    mimeType: 'application/json'
);

// 4. Simulate incoming request headers
$requestHeaders = [
    'Content-Type' => 'application/json',
    // If payment is included, it will be in X-Payment header
    // 'X-Payment' => 'base64_encoded_payment_payload',
];

// 5. Process payment
$result = $handler->processPayment($requestHeaders, $requirements);

if ($result['verified']) {
    // Payment verified successfully
    echo "Payment verified successfully!\n";
    
    if ($result['settlement'] !== null) {
        echo "Payment settled. Transaction: " . $result['settlement']['transaction'] . "\n";
    }
    
    // Serve the protected resource
    $response = [
        'status' => 'success',
        'data' => 'Your premium content here',
    ];
    
    // Add payment response header if settlement was successful
    if ($result['settlement'] !== null) {
        $paymentResponseHeader = $handler->createPaymentResponseHeader($result['settlement']);
        echo "Add header: " . $handler->getPaymentResponseHeaderName() . ": " . $paymentResponseHeader . "\n";
    }
    
    echo json_encode($response) . "\n";
} else {
    // Payment required
    echo "Payment required!\n";
    
    $paymentRequiredResponse = $handler->createPaymentRequiredResponse($requirements);
    
    // Return 402 status with payment requirements
    http_response_code(402);
    header('Content-Type: application/json');
    echo json_encode($paymentRequiredResponse) . "\n";
}
