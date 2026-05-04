<?php

declare(strict_types=1);

use Bloxy\Core\Tests\TestCase;

uses(TestCase::class)->in('Feature');
uses(TestCase::class)->in('Unit');

/**
 * Build a canned PSR-7 JSON response for FakeAnthropicTransport.
 *
 * Uses guzzlehttp/psr7 (a transitive dep of anthropic-ai/sdk via
 * php-http/discovery's PSR-17 implementation) — no extra dev dep needed.
 *
 * @param  array<string, mixed>  $body
 */
function fake_anthropic_response(array $body, int $status = 200): \Psr\Http\Message\ResponseInterface
{
    return new \GuzzleHttp\Psr7\Response(
        $status,
        ['Content-Type' => 'application/json'],
        json_encode($body, JSON_THROW_ON_ERROR),
    );
}
