<?php

declare(strict_types=1);

namespace X402\Tests\Types;

use PHPUnit\Framework\TestCase;
use X402\Types\PaymentRequiredResponse;
use X402\Types\PaymentRequirements;

class PaymentRequiredResponseTest extends TestCase
{
    public function testGetStatusCodeReturns402(): void
    {
        $response = new PaymentRequiredResponse(
            x402Version: 1,
            accepts: [
                new PaymentRequirements(scheme: 'exact')
            ]
        );

        self::assertSame(402, $response->getStatusCode());
    }

    public function testSendEmitsHeadersBeforeStatusAndBody(): void
    {
        $response = new PaymentRequiredResponse(
            x402Version: 1,
            accepts: [
                new PaymentRequirements(scheme: 'exact'),
                new PaymentRequirements(scheme: 'stream'),
            ],
            error: 'Needs payment'
        );

        $calls = [];
        $headers = [];
        $statusCode = null;
        $body = null;

        $response->send(
            headerSender: function (string $name, string $value) use (&$calls, &$headers): void {
                $calls[] = "header:{$name}";
                $headers[$name] = $value;
            },
            statusSender: function (int $code) use (&$calls, &$statusCode): void {
                $calls[] = 'status';
                $statusCode = $code;
            },
            bodySender: function (string $content) use (&$calls, &$body): void {
                $calls[] = 'body';
                $body = $content;
            }
        );

        self::assertSame(
            ['header:WWW-Authenticate', 'header:Content-Type', 'header:X-Payment-Accept', 'status', 'body'],
            $calls
        );
        self::assertSame(402, $statusCode);
        self::assertSame('X-Payment', $headers['WWW-Authenticate']);
        self::assertSame('application/json', $headers['Content-Type']);
        self::assertSame('exact, stream', $headers['X-Payment-Accept']);

        $decoded = json_decode($body, true);
        self::assertIsArray($decoded);
        self::assertSame(1, $decoded['x402Version']);
        self::assertSame('Needs payment', $decoded['error']);
    }
}
