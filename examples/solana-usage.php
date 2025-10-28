<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use X402\Facilitator\FacilitatorClient;
use X402\Middleware\PaymentHandler;

/**
 * Example: Solana/SPL Token payment integration
 * 
 * This example demonstrates how to accept payments on Solana using SPL tokens.
 */

// 1. Initialize facilitator client
// Option 1: Use Coinbase Facilitator (recommended)
$facilitator = FacilitatorClient::coinbase(
    apiKey: getenv('COINBASE_FACILITATOR_API_KEY')  // Optional for testing
);

// Option 2: Use from environment
// $facilitator = FacilitatorClient::fromEnvironment();

// Option 3: Self-hosted or custom facilitator
// $facilitator = new FacilitatorClient('https://your-facilitator.example.com');

// 2. Create payment handler
$handler = new PaymentHandler(
    facilitator: $facilitator,
    autoSettle: true
);

// 3. Define payment requirements for Solana
$payTo = 'HN7cABqLq46Es1jh92dQQisAq662SmxELLLsHHe4YWrH'; // Your Solana wallet address
$amount = '1000000'; // Amount in atomic units (1 USDC = 1000000 with 6 decimals)
$asset = 'EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v'; // USDC SPL Token on Solana Mainnet
$network = 'solana-mainnet';

$requirements = $handler->createPaymentRequirements(
    payTo: $payTo,
    amount: $amount,
    resource: 'https://your-api.com/solana-data',
    description: 'Premium API access (Solana/SPL)',
    asset: $asset,
    network: $network,
    scheme: 'exact',
    timeout: 300,
    mimeType: 'application/json',
    extra: [
        'feePayer' => 'FeePayerPubkeyHere...' // Optional: facilitator's fee payer address
    ],
    id: 'solana-payment-' . uniqid()  // Optional but recommended for Coinbase
);

// 4. Simulate incoming request headers
$requestHeaders = [
    'Content-Type' => 'application/json',
    // If payment is included, it will be in X-Payment header
    // The payload contains a base64-encoded Solana transaction
    // 'X-Payment' => 'base64_encoded_solana_transaction',
];

// 5. Process payment
$result = $handler->processPayment($requestHeaders, $requirements);

if ($result['verified']) {
    // Payment verified successfully
    echo "Solana payment verified successfully!\n";
    
    if ($result['settlement'] !== null) {
        echo "Payment settled. Transaction: " . $result['settlement']['transaction'] . "\n";
    }
    
    // Serve the protected resource
    $response = [
        'status' => 'success',
        'data' => 'Your premium Solana content here',
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
    
    // Return 402 status with required x402 protocol headers
    http_response_code(402);
    
    // Add all required x402 headers
    $headers = $paymentRequiredResponse->getHeaders();
    foreach ($headers as $name => $value) {
        header("{$name}: {$value}");
    }
    
    echo json_encode($paymentRequiredResponse) . "\n";
}
