<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create(config('tyro.tables.roles', 'roles'), function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->index();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists(config('tyro.tables.roles', 'roles'));
    }
};
