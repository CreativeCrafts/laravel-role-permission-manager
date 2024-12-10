<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        // Create roles table
        Schema::create(config('role-permission-manager.roles_table'), function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->timestamps();

            $table->foreign('parent_id')->references('id')->on(config('role-permission-manager.roles_table'))->onDelete(
                'set null'
            );
        });

        // Create permissions table
        Schema::create(config('role-permission-manager.permissions_table'), function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->string('scope')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['name', 'scope']);
            $table->unique(['slug', 'scope']);
        });

        // Create role_user table
        Schema::create(config('role-permission-manager.user_role_table'), function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();

            $table->foreign('role_id')->references('id')->on(config('role-permission-manager.roles_table'))->onDelete(
                'cascade'
            );
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->unique(['role_id', 'user_id']);
        });

        // Create permission_role table
        Schema::create(config('role-permission-manager.role_permission_table'), function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->timestamps();

            $table->foreign('permission_id')->references('id')->on(
                config('role-permission-manager.permissions_table')
            )->onDelete('cascade');
            $table->foreign('role_id')->references('id')->on(config('role-permission-manager.roles_table'))->onDelete(
                'cascade'
            );

            $table->unique(['permission_id', 'role_id']);
        });

        // Create user_permission table
        Schema::create(config('role-permission-manager.user_permission_table'), function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();

            $table->foreign('permission_id')->references('id')->on(
                config('role-permission-manager.permissions_table')
            )->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->unique(['permission_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('role-permission-manager.role_permission_table'));
        Schema::dropIfExists(config('role-permission-manager.user_role_table'));
        Schema::dropIfExists(config('role-permission-manager.permissions_table'));
        Schema::dropIfExists(config('role-permission-manager.roles_table'));
        Schema::dropIfExists(config('role-permission-manager.user_permission_table'));
    }
};
