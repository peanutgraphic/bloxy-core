<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_log', function (Blueprint $table): void {
            $table->char('signature', 64)->nullable()->after('meta');
            $table->smallInteger('signing_key_id')->nullable()->default(1)->after('signature');
        });
    }

    public function down(): void
    {
        Schema::table('audit_log', function (Blueprint $table): void {
            $table->dropColumn(['signature', 'signing_key_id']);
        });
    }
};
