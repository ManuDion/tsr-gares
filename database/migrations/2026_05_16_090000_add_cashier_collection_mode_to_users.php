<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'cashier_collection_mode')) {
                $table->string('cashier_collection_mode', 30)
                    ->default(User::CASHIER_COLLECTION_BOTH)
                    ->after('allow_multi_gare_entry');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'cashier_collection_mode')) {
                $table->dropColumn('cashier_collection_mode');
            }
        });
    }
};
