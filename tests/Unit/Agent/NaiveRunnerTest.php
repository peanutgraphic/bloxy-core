<?php

declare(strict_types=1);

use Bloxy\Core\Agent\Agent;
use Bloxy\Core\Agent\Concerns\HasDefaultVisibility;
use Bloxy\Core\Agent\Runners\NaiveRunner;

it('NaiveRunner calls $agent->invoke($params) directly and returns the result', function () {
    $runner = new NaiveRunner();

    $agent = new class implements Agent {
        use HasDefaultVisibility;
        public function name(): string { return 'demo.echo'; }
        public function description(): string { return 'echoes'; }
        public function invoke(array $params): array { return ['echoed' => $params]; }
    };

    $result = $runner->run($agent, ['x' => 1]);
    expect($result)->toBeInstanceOf(\Bloxy\Core\Agent\AgentRunResult::class);
    expect($result->result)->toBe(['echoed' => ['x' => 1]]);
    expect($result->promptTokens)->toBeNull();
});
