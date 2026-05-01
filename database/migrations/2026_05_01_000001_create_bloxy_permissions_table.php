<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bloxy_permissions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 150)->unique();
            $table->string('description', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('bloxy_role_permissions', function (Blueprint $table) {
            $table->foreignId('role_id')
                ->constrained('bloxy_roles')
                ->cascadeOnDelete();
            $table->foreignId('permission_id')
                ->constrained('bloxy_permissions')
                ->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bloxy_role_permissions');
        Schema::dropIfExists('bloxy_permissions');
    }
};
