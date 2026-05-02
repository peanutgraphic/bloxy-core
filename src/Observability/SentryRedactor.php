<?php

declare(strict_types=1);

namespace Bloxy\Core\Observability;

/**
 * Helper providing the `before_send` callback for Sentry's PHP SDK. When
 * registered, every Sentry event payload's STRUCTURED FIELDS are recursively
 * walked through `Redactor` before transmission, so sensitive fields in
 * request data, extra context, and breadcrumb metadata are replaced with
 * the redaction marker.
 *
 * Limitations:
 * - Free-text exception messages and `Sentry::captureMessage()` strings are
 *   NOT redacted — `Redactor` is key-name-driven and only walks structured
 *   arrays. Apps that need free-text PII redaction should sanitize at the
 *   call site (don't pass user input into exception messages or
 *   captureMessage strings).
 * - User context (`Sentry::setUser([...])`) and tags (`Sentry::setTag(...)`)
 *   are NOT walked in B1.6.0; both are common PII surfaces. Tracked for a
 *   follow-up; see the spec's deferred-items list.
 *
 * Apps wire this via Sentry's Laravel package config or via a manual
 * Sentry::init([...]) call. BloxyCoreServiceProvider auto-wires it when
 * Sentry's PHP SDK is installed (class_exists check) and
 * bloxy.observability.redaction.auto_wire_sentry is true (default).
 */
class SentryRedactor
{
    public function __construct(private readonly Redactor $redactor)
    {
    }

    /**
     * Returns a closure suitable for Sentry's `before_send` hook. The closure
     * recursively walks the event's user-controlled payload sections through
     * Redactor and returns the modified event.
     *
     * @return callable(object): ?object
     */
    public function beforeSend(): callable
    {
        return function (object $event): ?object {
            // Sentry's Event object has structured accessors; we go through
            // the public getter/setter pairs Sentry exposes so we don't depend
            // on internal property layout.
            $this->redactRequestPayload($event);
            $this->redactExtraContext($event);
            $this->redactBreadcrumbs($event);

            return $event;
        };
    }

    private function redactRequestPayload(object $event): void
    {
        if (! method_exists($event, 'getRequest') || ! method_exists($event, 'setRequest')) {
            return;
        }

        $request = $event->getRequest();
        if (! is_array($request)) {
            return;
        }

        $redacted = $this->redactor->redact($request);
        if (is_array($redacted)) {
            $event->setRequest($redacted);
        }
    }

    private function redactExtraContext(object $event): void
    {
        if (! method_exists($event, 'getExtra') || ! method_exists($event, 'setExtra')) {
            return;
        }

        $extra = $event->getExtra();
        if (! is_array($extra)) {
            return;
        }

        $redacted = $this->redactor->redact($extra);
        if (is_array($redacted)) {
            $event->setExtra($redacted);
        }
    }

    private function redactBreadcrumbs(object $event): void
    {
        if (! method_exists($event, 'getBreadcrumb') || ! method_exists($event, 'setBreadcrumb')) {
            return;
        }

        $breadcrumbs = $event->getBreadcrumb();
        if (! is_array($breadcrumbs)) {
            return;
        }

        $redactedAll = [];
        foreach ($breadcrumbs as $crumb) {
            if (is_object($crumb) && method_exists($crumb, 'getMetadata') && method_exists($crumb, 'withMetadata')) {
                $metadata = $crumb->getMetadata();
                if (is_array($metadata)) {
                    $redactedMetadata = $this->redactor->redact($metadata);
                    if (is_array($redactedMetadata)) {
                        // Sentry's Breadcrumb::withMetadata(string $name, mixed $value)
                        // takes a single key-value pair, not the full metadata array.
                        // Apply each redacted entry; the immutable-builder pattern
                        // returns a new crumb each call, so we accumulate.
                        foreach ($redactedMetadata as $key => $value) {
                            $crumb = $crumb->withMetadata((string) $key, $value);
                        }
                    }
                }
            }
            $redactedAll[] = $crumb;
        }

        $event->setBreadcrumb($redactedAll);
    }
}
