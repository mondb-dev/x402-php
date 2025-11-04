<?php

declare(strict_types=1);

namespace X402\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use X402\Types\PaymentRequiredResponse;

/**
 * PSR-7 HTTP response builder for x402 protocol.
 * 
 * Provides utilities to create PSR-7 compatible HTTP responses
 * for frameworks like Symfony, Laravel, Slim, etc.
 */
class Psr7ResponseBuilder
{
    /**
     * Create a 402 Payment Required PSR-7 response.
     *
     * @param PaymentRequiredResponse $paymentResponse Payment required response data
     * @param ResponseInterface $baseResponse Base PSR-7 response to build upon
     * @return ResponseInterface PSR-7 response with 402 status
     */
    public static function createPaymentRequired(
        PaymentRequiredResponse $paymentResponse,
        ResponseInterface $baseResponse
    ): ResponseInterface {
        $json = json_encode($paymentResponse->toArray(), JSON_THROW_ON_ERROR);
        
        $body = $baseResponse->getBody();
        $body->write($json);
        $body->rewind();

        return $baseResponse
            ->withStatus(402, 'Payment Required')
            ->withHeader('WWW-Authenticate', 'X-Payment')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($body);
    }

    /**
     * Create a 402 Payment Required PSR-7 response with stream body.
     *
     * @param PaymentRequiredResponse $paymentResponse Payment required response data
     * @param ResponseInterface $baseResponse Base PSR-7 response
     * @param StreamInterface $stream Stream to write body to
     * @return ResponseInterface PSR-7 response with 402 status
     */
    public static function createPaymentRequiredWithStream(
        PaymentRequiredResponse $paymentResponse,
        ResponseInterface $baseResponse,
        StreamInterface $stream
    ): ResponseInterface {
        $json = json_encode($paymentResponse->toArray(), JSON_THROW_ON_ERROR);
        
        $stream->write($json);
        $stream->rewind();

        return $baseResponse
            ->withStatus(402, 'Payment Required')
            ->withHeader('WWW-Authenticate', 'X-Payment')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($stream);
    }

    /**
     * Create a success response with payment metadata.
     *
     * @param mixed $data Response data
     * @param array<string, mixed> $paymentMetadata Payment metadata to include in headers
     * @param ResponseInterface $baseResponse Base PSR-7 response
     * @return ResponseInterface PSR-7 response with payment metadata
     */
    public static function createSuccessWithPaymentMetadata(
        mixed $data,
        array $paymentMetadata,
        ResponseInterface $baseResponse
    ): ResponseInterface {
        $json = json_encode($data, JSON_THROW_ON_ERROR);
        
        $body = $baseResponse->getBody();
        $body->write($json);
        $body->rewind();

        $response = $baseResponse
            ->withStatus(200, 'OK')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($body);

        // Add payment metadata headers
        if (isset($paymentMetadata['transactionHash'])) {
            $response = $response->withHeader(
                'X-Payment-Transaction',
                $paymentMetadata['transactionHash']
            );
        }

        if (isset($paymentMetadata['network'])) {
            $response = $response->withHeader(
                'X-Payment-Network',
                $paymentMetadata['network']
            );
        }

        if (isset($paymentMetadata['scheme'])) {
            $response = $response->withHeader(
                'X-Payment-Scheme',
                $paymentMetadata['scheme']
            );
        }

        return $response;
    }
}
