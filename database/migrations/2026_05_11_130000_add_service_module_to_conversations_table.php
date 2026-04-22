<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('conversations', 'service_module')) {
            Schema::table('conversations', function (Blueprint $table) {
                $table->string('service_module', 30)->nullable()->after('conversation_type');
                $table->index('service_module');
            });
        }

        DB::table('conversations')
            ->where('conversation_type', 'inter_service')
            ->update(['conversation_type' => 'service_internal']);
    }

    public function down(): void
    {
        DB::table('conversations')
            ->where('conversation_type', 'service_internal')
            ->update(['conversation_type' => 'inter_service']);

        if (Schema::hasColumn('conversations', 'service_module')) {
            Schema::table('conversations', function (Blueprint $table) {
                $table->dropIndex(['service_module']);
                $table->dropColumn('service_module');
            });
        }
    }
};
