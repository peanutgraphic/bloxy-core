<?php

declare(strict_types=1);

use Bloxy\Core\Audit\AuditLog;
use Bloxy\Core\Audit\AuditMiddleware;
use Illuminate\Http\Request;

it('records a request entry for POST', function () {
    $middleware = new AuditMiddleware();
    $request = Request::create('/foo', 'POST', server: ['HTTP_USER_AGENT' => 'Mozilla/test']);

    $response = $middleware->handle($request, fn ($r) => response('ok', 201));

    expect($response->getStatusCode())->toBe(201);

    $entries = AuditLog::query()->get();
    expect($entries)->toHaveCount(1);
    expect($entries->first()->action)->toBe('http_request');
    expect($entries->first()->meta['method'])->toBe('POST');
    expect($entries->first()->meta['path'])->toBe('foo');
    expect($entries->first()->meta['status'])->toBe(201);
});

it('records a request entry for PUT, PATCH, and DELETE', function () {
    $middleware = new AuditMiddleware();
    foreach (['PUT', 'PATCH', 'DELETE'] as $method) {
        $request = Request::create('/x', $method);
        $middleware->handle($request, fn ($r) => response('ok', 200));
    }

    $entries = AuditLog::query()->get();
    expect($entries)->toHaveCount(3);
});

it('does NOT record entries for GET, HEAD, OPTIONS', function () {
    $middleware = new AuditMiddleware();
    foreach (['GET', 'HEAD', 'OPTIONS'] as $method) {
        $request = Request::create('/x', $method);
        $middleware->handle($request, fn ($r) => response('ok', 200));
    }

    expect(AuditLog::query()->count())->toBe(0);
});

it('stashes a per-request UUID on the request attributes for HasAudit to pick up', function () {
    $middleware = new AuditMiddleware();
    $request = Request::create('/x', 'POST');

    $capturedRequestId = null;
    $middleware->handle($request, function (Request $r) use (&$capturedRequestId) {
        $capturedRequestId = $r->attributes->get('audit.request_id');
        return response('ok');
    });

    expect($capturedRequestId)->toBeString();
    expect($capturedRequestId)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/');

    $entry = AuditLog::query()->first();
    expect($entry->request_id)->toBe($capturedRequestId);
});

it('truncates user-agent to 1000 chars defensively', function () {
    $middleware = new AuditMiddleware();
    $longAgent = str_repeat('A', 5000);
    $request = Request::create('/x', 'POST', server: ['HTTP_USER_AGENT' => $longAgent]);

    $middleware->handle($request, fn ($r) => response('ok'));

    $entry = AuditLog::query()->first();
    expect(strlen($entry->user_agent))->toBeLessThanOrEqual(1000);
});

it('records the IP address from the request', function () {
    $middleware = new AuditMiddleware();
    $request = Request::create('/x', 'POST', server: ['REMOTE_ADDR' => '203.0.113.42']);

    $middleware->handle($request, fn ($r) => response('ok'));

    $entry = AuditLog::query()->first();
    expect($entry->ip_address)->toBe('203.0.113.42');
});
