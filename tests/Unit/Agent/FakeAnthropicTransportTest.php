<?php

declare(strict_types=1);

use Bloxy\Core\Tests\Support\Agent\FakeAnthropicTransport;

it('FakeAnthropicTransport returns queued responses in order', function () {
    $transport = new FakeAnthropicTransport();
    $r1 = fake_anthropic_response(['msg' => 'first']);
    $r2 = fake_anthropic_response(['msg' => 'second']);
    $transport->queue($r1)->queue($r2);

    $req = new \GuzzleHttp\Psr7\Request('POST', 'https://example.com', [], 'body-1');
    $resp1 = $transport->sendRequest($req);
    expect((string) $resp1->getBody())->toContain('first');

    $resp2 = $transport->sendRequest(
        new \GuzzleHttp\Psr7\Request('POST', 'https://example.com', [], 'body-2'),
    );
    expect((string) $resp2->getBody())->toContain('second');
});

it('throws if the queue is empty', function () {
    $transport = new FakeAnthropicTransport();
    expect(fn () => $transport->sendRequest(new \GuzzleHttp\Psr7\Request('POST', 'https://example.com')))
        ->toThrow(\LogicException::class);
});

it('captures request bodies for assertion', function () {
    $transport = new FakeAnthropicTransport();
    $transport->queue(fake_anthropic_response(['ok' => true]));

    $req = new \GuzzleHttp\Psr7\Request(
        'POST',
        'https://example.com',
        ['Content-Type' => 'application/json'],
        json_encode(['hello' => 'world']),
    );
    $transport->sendRequest($req);

    expect($transport->lastRequestBody())->toBe(['hello' => 'world']);
});
