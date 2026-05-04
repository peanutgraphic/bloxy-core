<?php

declare(strict_types=1);

use Bloxy\Core\Agent\Agent;
use Bloxy\Core\Agent\Concerns\HasDefaultVisibility;
use Bloxy\Core\Agent\Runners\NaiveRunner;
use Bloxy\Core\Agent\UsageLog\AgentUsageLog;
use Bloxy\Core\Agent\UsageLog\UsageLogger;

class UsageTestAgent implements Agent {
    use HasDefaultVisibility;
    public function name(): string { return 'demo.usage'; }
    public function description(): string { return 'usage'; }
    public function invoke(array $params): array { return ['ok' => true]; }
}

it('UsageLogger writes a row with the supplied fields', function () {
    $logger = app(UsageLogger::class);
    $logger->record(
        agent: new UsageTestAgent(),
        runnerClass: NaiveRunner::class,
        actorType: 'users',
        actorId: 'user-1',
        outcome: 'success',
        promptTokens: null,
        completionTokens: null,
        costUsdCents: null,
    );

    $row = AgentUsageLog::query()->latest('id')->first();
    expect($row)->not->toBeNull();
    expect($row->agent_name)->toBe('demo.usage');
    expect($row->runner_class)->toBe(NaiveRunner::class);
    expect($row->actor_type)->toBe('users');
    expect($row->actor_id)->toBe('user-1');
    expect($row->outcome)->toBe('success');
});
