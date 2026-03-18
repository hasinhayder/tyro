<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        $userTable = config('tyro.tables.users', 'users');

        Schema::table($userTable, function (Blueprint $table) use ($userTable) {
            if (! Schema::hasColumn($userTable, 'suspended_at')) {
                $table->timestamp('suspended_at')->nullable();
            }

            if (! Schema::hasColumn($userTable, 'suspension_reason')) {
                $table->text('suspension_reason')->nullable();
            }
        });
    }

    public function down(): void {
        $userTable = config('tyro.tables.users', 'users');

        Schema::table($userTable, function (Blueprint $table) use ($userTable) {
            if (Schema::hasColumn($userTable, 'suspension_reason')) {
                $table->dropColumn('suspension_reason');
            }

            if (Schema::hasColumn($userTable, 'suspended_at')) {
                $table->dropColumn('suspended_at');
            }
        });
    }
};
