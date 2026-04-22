<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('conversations', 'conversation_type')) {
            Schema::table('conversations', function (Blueprint $table) {
                $table->string('conversation_type', 30)->default('direct')->after('is_group');
                $table->index('conversation_type');
            });
        }

        DB::table('conversations')->update([
            'conversation_type' => DB::raw("CASE WHEN is_group = 1 THEN 'inter_service' ELSE 'direct' END"),
        ]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('conversations', 'conversation_type')) {
            Schema::table('conversations', function (Blueprint $table) {
                $table->dropIndex(['conversation_type']);
                $table->dropColumn('conversation_type');
            });
        }
    }
};
