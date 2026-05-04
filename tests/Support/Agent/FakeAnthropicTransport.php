<?php

declare(strict_types=1);

namespace Bloxy\Core\Tests\Support\Agent;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * PSR-18 test double for the Anthropic SDK.
 *
 * The SDK accepts a transporter (Psr\Http\Client\ClientInterface) via
 * RequestOptions::with(transporter: ...) — see Anthropic\Client and
 * Anthropic\RequestOptions::withTransporter(). Pre-load with queue()
 * responses; the SDK pulls one off the queue per call. Each request is
 * captured for assertion via lastRequest() / requests().
 */
final class FakeAnthropicTransport implements ClientInterface
{
    /** @var array<int, ResponseInterface> */
    private array $queued = [];

    /** @var array<int, RequestInterface> */
    private array $captured = [];

    public function queue(ResponseInterface $response): self
    {
        $this->queued[] = $response;
        return $this;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->captured[] = $request;

        if ($this->queued === []) {
            throw new \LogicException(
                'FakeAnthropicTransport: queue empty. Pre-load responses with queue().',
            );
        }

        return array_shift($this->queued);
    }

    public function lastRequest(): ?RequestInterface
    {
        if ($this->captured === []) {
            return null;
        }

        return $this->captured[array_key_last($this->captured)];
    }

    /** @return array<int, RequestInterface> */
    public function requests(): array
    {
        return $this->captured;
    }

    /**
     * Decode the last request's JSON body. Convenience for assertions.
     *
     * @return array<string, mixed>
     */
    public function lastRequestBody(): array
    {
        $req = $this->lastRequest();
        if ($req === null) {
            return [];
        }

        $decoded = json_decode((string) $req->getBody(), true);

        return is_array($decoded) ? $decoded : [];
    }
}
