<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('gares', function (Blueprint $table) {
            if (! Schema::hasColumn('gares', 'activity_mode')) {
                $table->string('activity_mode', 20)->default('mixed')->after('cashier_user_id');
                $table->index('activity_mode');
            }
        });
    }

    public function down(): void
    {
        Schema::table('gares', function (Blueprint $table) {
            if (Schema::hasColumn('gares', 'activity_mode')) {
                try {
                    $table->dropIndex('gares_activity_mode_index');
                } catch (\Throwable $e) {
                }
                $table->dropColumn('activity_mode');
            }
        });
    }
};

