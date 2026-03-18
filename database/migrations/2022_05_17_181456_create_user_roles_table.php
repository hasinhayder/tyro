<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        $pivot = config('tyro.tables.pivot', 'user_roles');
        Schema::create($pivot, function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained(config('tyro.tables.roles', 'roles'))->cascadeOnDelete();
            $table->unique(['user_id', 'role_id']);
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists(config('tyro.tables.pivot', 'user_roles'));
    }
};
