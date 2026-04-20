<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('recettes', function (Blueprint $table) {
            $table->decimal('ticket_inter_amount', 14, 2)->default(0)->after('amount');
            $table->decimal('ticket_national_amount', 14, 2)->default(0)->after('ticket_inter_amount');
            $table->decimal('bagage_inter_amount', 14, 2)->default(0)->after('ticket_national_amount');
            $table->decimal('bagage_national_amount', 14, 2)->default(0)->after('bagage_inter_amount');
        });

        DB::table('recettes')->update([
            'ticket_inter_amount' => DB::raw('amount'),
            'ticket_national_amount' => 0,
            'bagage_inter_amount' => 0,
            'bagage_national_amount' => 0,
        ]);
    }

    public function down(): void
    {
        Schema::table('recettes', function (Blueprint $table) {
            $table->dropColumn([
                'ticket_inter_amount',
                'ticket_national_amount',
                'bagage_inter_amount',
                'bagage_national_amount',
            ]);
        });
    }
};
