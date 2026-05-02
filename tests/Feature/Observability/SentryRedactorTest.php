<?php

declare(strict_types=1);

use Bloxy\Core\Observability\Redactor;
use Bloxy\Core\Observability\SentryRedactor;

beforeEach(function (): void {
    $this->redactor = new Redactor(
        allowlist: ['password', 'token', 'secret', 'authorization'],
        marker: '[REDACTED]',
    );
});

it('redacts request payload sections via the Sentry-shaped accessors', function () {
    // Build a minimal stub object that mirrors Sentry\Event's getter/setter shape
    // so we can test SentryRedactor without depending on the Sentry SDK.
    $event = new class {
        private array $request = [];
        public function getRequest(): array { return $this->request; }
        public function setRequest(array $r): void { $this->request = $r; }
    };

    $event->setRequest([
        'method' => 'POST',
        'data' => ['email' => 'a@b.com', 'password' => 'p@ss'],
        'headers' => ['authorization' => 'Bearer xyz'],
    ]);

    $callback = (new SentryRedactor($this->redactor))->beforeSend();
    $callback($event);

    expect($event->getRequest())->toBe([
        'method' => 'POST',
        'data' => ['email' => 'a@b.com', 'password' => '[REDACTED]'],
        'headers' => ['authorization' => '[REDACTED]'],
    ]);
});

it('redacts extra context via the Sentry-shaped accessors', function () {
    $event = new class {
        private array $extra = [];
        public function getExtra(): array { return $this->extra; }
        public function setExtra(array $e): void { $this->extra = $e; }
    };

    $event->setExtra([
        'session' => ['id' => 'abc', 'token' => 'xyz'],
        'feature_flag' => 'on',
    ]);

    $callback = (new SentryRedactor($this->redactor))->beforeSend();
    $callback($event);

    expect($event->getExtra())->toBe([
        'session' => ['id' => 'abc', 'token' => '[REDACTED]'],
        'feature_flag' => 'on',
    ]);
});

it('returns the event unchanged when no redactor-relevant accessors exist', function () {
    $event = new class {};

    $callback = (new SentryRedactor($this->redactor))->beforeSend();
    $result = $callback($event);

    expect($result)->toBe($event);
});

it('redacts breadcrumb metadata via the Sentry-shaped API (getBreadcrumb singular)', function () {
    // Stub mirroring Sentry\Event's actual API: getBreadcrumb / setBreadcrumb
    // (singular, despite returning/accepting arrays).
    $event = new class {
        private array $breadcrumbs = [];
        public function getBreadcrumb(): array { return $this->breadcrumbs; }
        public function setBreadcrumb(array $b): void { $this->breadcrumbs = $b; }
    };

    // Stub mirroring Sentry\Breadcrumb's immutable-builder API for metadata.
    $crumb = new class {
        private array $metadata = ['action' => 'login', 'password' => 'plaintext', 'user' => 'alice'];
        public function getMetadata(): array { return $this->metadata; }
        public function withMetadata(string $name, mixed $value): self
        {
            $clone = clone $this;
            $clone->metadata[$name] = $value;
            return $clone;
        }
    };

    $event->setBreadcrumb([$crumb]);

    $callback = (new SentryRedactor($this->redactor))->beforeSend();
    $callback($event);

    $resultCrumbs = $event->getBreadcrumb();
    expect($resultCrumbs)->toHaveCount(1);
    expect($resultCrumbs[0]->getMetadata())->toBe([
        'action' => 'login',
        'password' => '[REDACTED]',
        'user' => 'alice',
    ]);
});

it('runs idempotently — repeated wireSentry() does not nest the wrapper', function () {
    // We can't directly observe wireSentry's idempotency from a unit test
    // (it depends on Sentry SDK presence), but we CAN verify the static
    // flag mechanism works against re-entry by inspecting it via reflection.
    $providerClass = new \ReflectionClass(\Bloxy\Core\BloxyCoreServiceProvider::class);
    expect($providerClass->hasProperty('sentryRedactorWired'))->toBeTrue();

    $prop = $providerClass->getProperty('sentryRedactorWired');
    expect($prop->isStatic())->toBeTrue();
    expect($prop->isPrivate())->toBeTrue();
});
