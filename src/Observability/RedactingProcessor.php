<?php

declare(strict_types=1);

namespace Bloxy\Core\Observability;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Monolog processor that walks `context` and `extra` arrays through
 * `Bloxy\Core\Observability\Redactor`. Registered against the default
 * Monolog Logger by BloxyCoreServiceProvider when
 * bloxy.observability.redaction.auto_wire_monolog is true (default).
 *
 * Without this, sensitive data passed in log context (e.g.,
 * Log::warning('failed', ['password' => $request->input('password')]))
 * would land in the application log in plaintext. With it, every Monolog
 * sink (file, stderr, Slack, etc.) sees the redacted form automatically.
 */
class RedactingProcessor implements ProcessorInterface
{
    public function __construct(private readonly Redactor $redactor)
    {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        $context = $this->redactor->redact($record->context);
        $extra = $this->redactor->redact($record->extra);

        return $record->with(
            context: is_array($context) ? $context : $record->context,
            extra: is_array($extra) ? $extra : $record->extra,
        );
    }
}
