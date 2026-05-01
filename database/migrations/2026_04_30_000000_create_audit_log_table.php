<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_log', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->dateTime('happened_at');

            $table->string('actor_type', 50)->nullable();
            $table->string('actor_id', 100)->nullable();

            $table->string('action', 50);

            $table->string('subject_type', 200)->nullable();
            $table->string('subject_id', 100)->nullable();

            // EncryptedJson cast on the AuditLog model encrypts before write.
            $table->text('changes')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->uuid('request_id')->nullable();

            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index('happened_at');
            $table->index(['actor_type', 'actor_id']);
            $table->index(['subject_type', 'subject_id']);
            $table->index('request_id');
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_log');
    }
};
