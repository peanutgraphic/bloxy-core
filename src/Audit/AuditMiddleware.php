<?php

declare(strict_types=1);

namespace Bloxy\Core\Audit;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Audit-log every state-changing HTTP request.
 *
 * - GET/HEAD/OPTIONS pass through with no audit entry (would be noise).
 * - POST/PUT/PATCH/DELETE produce one `http_request` entry recording method,
 *   path, status, IP, user-agent, and a per-request UUID.
 * - The UUID is stashed on `$request->attributes` under `audit.request_id`
 *   before the inner handler runs, so any `HasAudit`-using model that fires
 *   during the request will record the same UUID and be correlatable.
 */
class AuditMiddleware
{
    private const STATE_CHANGING_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];
    private const USER_AGENT_MAX_LENGTH = 1000;

    public function handle(Request $request, Closure $next): Response
    {
        $isStateChanging = in_array($request->method(), self::STATE_CHANGING_METHODS, true);

        if (! $isStateChanging) {
            return $next($request);
        }

        $requestId = (string) Str::uuid();
        $request->attributes->set('audit.request_id', $requestId);

        $response = $next($request);

        $this->record($request, $response, $requestId);

        return $response;
    }

    private function record(Request $request, Response $response, string $requestId): void
    {
        $actor = $this->resolveActor();
        $userAgent = $request->userAgent();
        if (is_string($userAgent) && strlen($userAgent) > self::USER_AGENT_MAX_LENGTH) {
            $userAgent = substr($userAgent, 0, self::USER_AGENT_MAX_LENGTH);
        }

        AuditLog::create([
            'happened_at' => Carbon::now(),
            'actor_type' => $actor['type'],
            'actor_id' => $actor['id'],
            'action' => 'http_request',
            'subject_type' => null,
            'subject_id' => null,
            'changes' => null,
            'ip_address' => $request->ip(),
            'user_agent' => $userAgent,
            'request_id' => $requestId,
            'meta' => array_merge(
                [
                    'method' => $request->method(),
                    'path' => $request->path(),
                    'status' => $response->getStatusCode(),
                ],
                $this->captureBody($request),
            ),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function captureBody(Request $request): array
    {
        $mode = config('bloxy.audit.capture_request_body');
        if ($mode !== 'redacted' && $mode !== 'full') {
            return [];
        }

        $contentType = (string) $request->header('Content-Type', '');

        // Multipart uploads — never capture the actual content.
        // Check both header AND files collection: Laravel's test client
        // populates $request->files for fake uploads but doesn't set the
        // Content-Type header, so the bare header check would let
        // UploadedFile objects flow into $request->all() and crash JSON
        // encoding of meta.
        if (str_starts_with($contentType, 'multipart/form-data') || $request->files->count() > 0) {
            return ['body' => ['_omitted' => 'multipart/form-data']];
        }

        $maxBytes = (int) config('bloxy.audit.request_body_max_bytes', 65536);

        // Pre-check Content-Length to avoid loading huge bodies into memory
        // for the strlen() check. Header may be absent or wrong (chunked
        // encoding, malicious clients), so the strlen() guard below still
        // runs as the authoritative check.
        $contentLength = (int) $request->header('Content-Length', 0);
        if ($contentLength > $maxBytes) {
            return ['body_truncated' => true];
        }

        $raw = (string) $request->getContent();
        if (strlen($raw) > $maxBytes) {
            return ['body_truncated' => true];
        }

        // JSON path: decode the raw body. Form / other paths: use $request->all()
        // (which intentionally includes query string params — they're submission
        // data too, and the redundancy with meta.path is acceptable).
        if (str_starts_with($contentType, 'application/json')) {
            try {
                $decoded = json_decode($raw, associative: true, flags: JSON_THROW_ON_ERROR);
                $body = is_array($decoded) ? $decoded : ['_raw' => $raw];
            } catch (\JsonException) {
                // Genuine malformed JSON — store the raw bytes (capped at $maxBytes
                // already by the size guard) for forensic value.
                $body = ['_raw' => $raw];
            }
        } else {
            $body = $request->all();
        }

        if ($mode === 'redacted') {
            $body = app(\Bloxy\Core\Observability\Redactor::class)->redact($body);
        }

        return ['body' => $body];
    }

    /** @return array{type: ?string, id: ?string} */
    private function resolveActor(): array
    {
        if (! function_exists('auth')) {
            return ['type' => null, 'id' => null];
        }

        $user = auth()->user();
        if ($user === null) {
            return ['type' => null, 'id' => null];
        }

        return [
            'type' => method_exists($user, 'getMorphClass') ? $user->getMorphClass() : 'user',
            'id' => (string) $user->getKey(),
        ];
    }
}
