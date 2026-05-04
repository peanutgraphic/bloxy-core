<?php

declare(strict_types=1);

namespace Bloxy\Core\Agent\UsageLog;

use Illuminate\Database\Eloquent\Model;

class AgentUsageLog extends Model
{
    protected $table = 'agent_usage_log';
    protected $guarded = [];
    public $timestamps = true;

    protected function casts(): array
    {
        return [
            'happened_at' => 'datetime',
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'cost_usd_cents' => 'integer',
        ];
    }
}
