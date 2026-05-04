<?php

declare(strict_types=1);

namespace Bloxy\Core\Agent;

use Bloxy\Core\Agent\Audit\AgentAuditActions;
use Bloxy\Core\Agent\Exceptions\AgentAuthorizationDeniedException;
use Bloxy\Core\Agent\Exceptions\AgentInvocationFailedException;
use Bloxy\Core\Agent\Exceptions\AgentNameConflictException;
use Bloxy\Core\Agent\Exceptions\AgentNotFoundException;
use Bloxy\Core\Agent\UsageLog\UsageLogger;
use Bloxy\Core\Audit\AuditLog;
use Bloxy\Core\Observability\Redactor;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Support\Carbon;
use JsonException;
use Throwable;

final class InMemoryAgentRegistry implements AgentRegistry
{
    /** @var array<string, Agent> */
    private array $agents = [];

    public function __construct(
        private readonly AgentAuthorizer $authorizer,
        private readonly AgentRunner $runner,
        private readonly UsageLogger $usageLogger,
        private readonly Redactor $redactor,
        private readonly AuthFactory $auth,
    ) {}

    public function register(Agent $agent): void
    {
        $name = $agent->name();
        if (isset($this->agents[$name])) {
            throw AgentNameConflictException::for($name);
        }
        $this->agents[$name] = $agent;
    }

    public function all(): array
    {
        return $this->agents;
    }

    public function find(string $name): ?Agent
    {
        return $this->agents[$name] ?? null;
    }

    public function forSurface(string $surface): array
    {
        $out = [];
        foreach ($this->agents as $name => $agent) {
            if (in_array($surface, $agent->visibleIn(), true)) {
                $out[$name] = $agent;
            }
        }
        return $out;
    }

    public function invoke(string $name, array $params): array
    {
        $agent = $this->find($name);
        if ($agent === null) {
            throw AgentNotFoundException::for($name);
        }

        if (! $this->authorizer->mayInvoke($agent, $params)) {
            $this->safeEmitFailureTelemetry(AgentAuditActions::AGENT_INVOKE_DENIED, $agent, $params, [], 'denied');
            throw AgentAuthorizationDeniedException::for($name);
        }

        try {
            $runResult = $this->runner->run($agent, $params);
        } catch (Throwable $e) {
            $this->safeEmitFailureTelemetry(
                AgentAuditActions::AGENT_INVOKE_FAILED,
                $agent,
                $params,
                ['exception' => $e::class],
                'failed',
            );
            throw AgentInvocationFailedException::wrap($name, $e);
        }

        $resultMeta = $this->resultHashMeta($runResult->result);
        $this->emitAudit(AgentAuditActions::AGENT_INVOKE, $agent, $params, $resultMeta);
        $this->logUsage(
            $agent,
            'success',
            promptTokens: $runResult->promptTokens,
            completionTokens: $runResult->completionTokens,
            costUsdCents: $runResult->costUsdCents,
        );

        return $runResult->result;
    }

    /** @param array<string, mixed> $params  @param array<string, mixed> $extraMeta */
    private function emitAudit(string $action, Agent $agent, array $params, array $extraMeta): void
    {
        [$actorType, $actorId] = $this->actorTuple();
        AuditLog::query()->create([
            'happened_at' => Carbon::now(),
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'action' => $action,
            'subject_type' => AgentRegistry::class,
            'subject_id' => $agent->name(),
            'changes' => null,
            'meta' => array_merge(
                ['params' => $this->redactor->redact($params)],
                $extraMeta,
            ),
        ]);
    }

    private function logUsage(
        Agent $agent,
        string $outcome,
        ?int $promptTokens = null,
        ?int $completionTokens = null,
        ?int $costUsdCents = null,
    ): void {
        [$actorType, $actorId] = $this->actorTuple();
        $this->usageLogger->record(
            agent: $agent,
            runnerClass: $this->runner::class,
            actorType: $actorType,
            actorId: $actorId,
            outcome: $outcome,
            promptTokens: $promptTokens,
            completionTokens: $completionTokens,
            costUsdCents: $costUsdCents,
        );
    }

    /**
     * Resolve the current request's actor for audit/usage rows.
     *
     * Returns [actor_type, actor_id] tuple — both null if no user is
     * authenticated, or if the user lacks getMorphClass() (we record null
     * rather than silently labeling a stranger as 'users'; the consistency
     * with Authorizer.fail-loud lives there, audit just stays honest).
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function actorTuple(): array
    {
        $user = $this->auth->guard()->user();
        if ($user === null) {
            return [null, null];
        }

        if (! method_exists($user, 'getMorphClass')) {
            return [null, (string) $user->getAuthIdentifier()];
        }

        return [$user->getMorphClass(), (string) $user->getAuthIdentifier()];
    }

    /**
     * Emit failure-path audit + usage telemetry. Never throws — audit-infra
     * failures must not mask the original failure cause that the caller is
     * about to see. If either write fails, log to error_log() and continue.
     *
     * @param array<string, mixed> $params
     * @param array<string, mixed> $extraMeta
     */
    private function safeEmitFailureTelemetry(
        string $action,
        Agent $agent,
        array $params,
        array $extraMeta,
        string $usageOutcome,
    ): void {
        try {
            $this->emitAudit($action, $agent, $params, $extraMeta);
        } catch (Throwable $auditError) {
            error_log(sprintf(
                '[bloxy-agent] failed to emit %s audit row for agent [%s]: %s',
                $action,
                $agent->name(),
                $auditError->getMessage(),
            ));
        }

        try {
            $this->logUsage($agent, $usageOutcome);
        } catch (Throwable $usageError) {
            error_log(sprintf(
                '[bloxy-agent] failed to write %s usage row for agent [%s]: %s',
                $usageOutcome,
                $agent->name(),
                $usageError->getMessage(),
            ));
        }
    }

    /**
     * Compute the audit meta entries for a successful result. Tolerates
     * non-serializable results so a successful agent invocation never
     * surfaces a JsonException to the caller — instead, the audit row
     * carries result_sha256=null + result_serialization_error.
     *
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    private function resultHashMeta(array $result): array
    {
        try {
            return [
                'result_sha256' => hash('sha256', json_encode($result, JSON_THROW_ON_ERROR)),
            ];
        } catch (JsonException $e) {
            return [
                'result_sha256' => null,
                'result_serialization_error' => $e::class,
            ];
        }
    }
}
