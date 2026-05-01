<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bloxy_role_assignments', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->string('user_type', 200);
            $table->string('user_id', 100);

            $table->foreignId('role_id')
                ->constrained('bloxy_roles')
                ->cascadeOnDelete();

            // Resource-scoped grants: null = global (entire tenant / role-as-such),
            // non-null = scoped to a specific resource.
            $table->string('resource_type', 200)->nullable();
            $table->string('resource_id', 100)->nullable();

            // Conditional-grant slot. null = always active. Non-null JSON is
            // evaluated by a future predicate engine (M1.4+); until then the
            // resolver treats any non-null predicate as "inactive."
            $table->json('activation_predicate')->nullable();

            $table->timestamps();

            $table->index(['user_type', 'user_id']);
            $table->index(['resource_type', 'resource_id']);
            $table->unique(
                ['user_type', 'user_id', 'role_id', 'resource_type', 'resource_id'],
                'bloxy_role_assignments_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bloxy_role_assignments');
    }
};
