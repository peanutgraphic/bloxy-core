<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_usage_log', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->dateTime('happened_at');

            $table->string('actor_type', 50)->nullable();
            $table->string('actor_id', 100)->nullable();

            $table->string('agent_name', 100);
            $table->string('runner_class', 200);

            $table->integer('prompt_tokens')->nullable();
            $table->integer('completion_tokens')->nullable();
            $table->integer('cost_usd_cents')->nullable();

            $table->string('outcome', 50);

            $table->timestamps();

            $table->index('happened_at');
            $table->index(['actor_type', 'actor_id']);
            $table->index(['agent_name', 'happened_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_usage_log');
    }
};
