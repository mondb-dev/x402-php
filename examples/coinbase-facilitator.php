<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use X402\Facilitator\FacilitatorClient;
use X402\Middleware\PaymentHandler;
use X402\Types\PaymentRequirements;
use X402\Exceptions\PaymentRequiredException;

/**
 * Example: Using Coinbase Facilitator for x402 Payments
 * 
 * This example demonstrates integration with Coinbase's official facilitator service.
 */

echo "=== Coinbase Facilitator Example ===\n\n";

// 1. Create Coinbase Facilitator client
echo "1. Connecting to Coinbase Facilitator...\n";
$facilitator = FacilitatorClient::coinbase(
    apiKey: getenv('COINBASE_FACILITATOR_API_KEY')  // Optional for testing, required for production
);

// 2. Check supported configurations
echo "2. Fetching supported configurations...\n";
try {
    $supported = $facilitator->getSupported();
    
    echo "   Facilitator Version: {$supported->version}\n";
    echo "   Supported Schemes: " . implode(', ', $supported->schemes) . "\n";
    echo "   Supported Networks:\n";
    
    foreach ($supported->networks as $network) {
        echo "     - {$network->name} ({$network->id})\n";
        echo "       Chain ID: {$network->chainId}\n";
        echo "       Type: {$network->type}\n";
        if ($network->explorerUrl) {
            echo "       Explorer: {$network->explorerUrl}\n";
        }
    }
    
    echo "\n   Features:\n";
    foreach ($supported->features as $feature => $enabled) {
        $status = $enabled ? '✅' : '❌';
        echo "     $status $feature\n";
    }
    
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n";
    exit(1);
}

// 3. Create payment handler with Coinbase Facilitator
echo "\n3. Creating payment handler...\n";
$handler = new PaymentHandler(
    facilitator: $facilitator,
    autoSettle: true  // Let Coinbase handle settlement
);

// 4. Create payment requirement with unique ID (required by Coinbase)
echo "4. Creating payment requirement...\n";
$paymentId = 'payment-' . uniqid();
$requirements = new PaymentRequirements(
    id: $paymentId,  // ← Required by Coinbase Facilitator
    scheme: 'exact',
    network: 'base-sepolia',  // Using testnet for demo
    maxAmountRequired: '1000000', // $1.00 USDC (6 decimals)
    resource: 'https://api.example.com/premium-data',
    description: 'Premium API access',
    mimeType: 'application/json',
    payTo: '0x209693Bc6afc0C5328bA36FaF03C514EF312287C',  // Your receiving address
    maxTimeoutSeconds: 300,
    asset: '0x036CbD53842c5426634e7929541eC2318f3dCF7e', // USDC on Base Sepolia
    extra: [
        'name' => 'USD Coin',      // Required for EIP-712
        'version' => '2'            // Required for EIP-712
    ]
);

echo "   Payment ID: {$paymentId}\n";
echo "   Amount: $1.00 USDC\n";
echo "   Network: base-sepolia\n";

// 5. Create 402 Payment Required response
echo "\n5. Generating 402 Payment Required response...\n";
$paymentRequiredResponse = $handler->createPaymentRequiredResponse($requirements);

// Simulate sending 402 response
echo "   HTTP/1.1 402 Payment Required\n";
foreach ($paymentRequiredResponse->getHeaders() as $name => $value) {
    echo "   {$name}: {$value}\n";
}
echo "   \n";
echo "   " . json_encode($paymentRequiredResponse, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

// 6. Simulate receiving payment (in real app, this comes from client)
echo "\n6. Waiting for payment from client...\n";
echo "   (In production, client would send X-Payment header with signed authorization)\n";

// Simulate payment header from client (this would be real in production)
$simulatedHeaders = [
    'Content-Type' => 'application/json',
    // 'X-Payment' => 'base64_encoded_payment_from_client',  // Real payment would be here
];

// 7. Process payment
echo "\n7. Processing payment...\n";
try {
    $result = $handler->processPayment($simulatedHeaders, $requirements);
    
    if ($result['verified']) {
        echo "   ✅ Payment Verified!\n";
        
        // Display settlement information (Coinbase provides detailed info)
        if ($result['settlement'] !== null) {
            $settlement = $result['settlement'];
            echo "\n   Settlement Details:\n";
            echo "   Transaction Hash: {$settlement->transaction}\n";
            echo "   Network: {$settlement->network}\n";
            echo "   Status: {$settlement->status}\n";
            
            if ($settlement->submittedAt) {
                echo "   Submitted At: {$settlement->submittedAt}\n";
            }
            
            if ($settlement->explorerUrl) {
                echo "   Block Explorer: {$settlement->explorerUrl}\n";
            }
            
            // Check transaction status
            if ($settlement->isPending()) {
                echo "   ⏳ Transaction is pending confirmation...\n";
            } elseif ($settlement->isConfirmed()) {
                echo "   ✅ Transaction confirmed on-chain!\n";
            } elseif ($settlement->isFailed()) {
                echo "   ❌ Transaction failed: {$settlement->errorReason}\n";
            }
        }
        
        // Serve the protected resource
        echo "\n   Serving protected resource...\n";
        $response = [
            'status' => 'success',
            'data' => 'Your premium content here',
            'paymentId' => $paymentId,
        ];
        
        echo "   " . json_encode($response, JSON_PRETTY_PRINT) . "\n";
        
    } else {
        echo "   ❌ No valid payment received\n";
        echo "   Client needs to send payment with X-Payment header\n";
    }
    
} catch (PaymentRequiredException $e) {
    echo "   ❌ Payment verification failed\n";
    echo "   Reason: " . $e->getMessage() . "\n";
    
    // Get detailed error information from Coinbase
    $verifyResponse = $e->getVerifyResponse();
    if ($verifyResponse && $verifyResponse->getDetails()) {
        echo "\n   Detailed Error Information:\n";
        foreach ($verifyResponse->getDetails() as $key => $value) {
            echo "     {$key}: " . (is_string($value) ? $value : json_encode($value)) . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== Example Complete ===\n";
echo "\nTo test with real payments:\n";
echo "1. Get API key from https://www.coinbase.com/cloud\n";
echo "2. Set COINBASE_FACILITATOR_API_KEY environment variable\n";
echo "3. Use x402 client library to create and send payment\n";
echo "4. Payment will be verified and settled by Coinbase Facilitator\n";
