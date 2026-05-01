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
            'meta' => [
                'method' => $request->method(),
                'path' => $request->path(),
                'status' => $response->getStatusCode(),
            ],
        ]);
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
