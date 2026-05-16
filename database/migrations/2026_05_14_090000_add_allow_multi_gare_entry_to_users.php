<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'allow_multi_gare_entry')) {
                $table->boolean('allow_multi_gare_entry')->default(false)->after('gare_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'allow_multi_gare_entry')) {
                $table->dropColumn('allow_multi_gare_entry');
            }
        });
    }
};
