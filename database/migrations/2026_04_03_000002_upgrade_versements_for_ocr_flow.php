<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('versement_bancaires', function (Blueprint $table) {
            $table->date('receipt_date')->nullable()->after('operation_date');
            $table->timestamp('force_unlocked_until')->nullable()->after('updated_by');
            $table->text('unlock_reason')->nullable()->after('force_unlocked_until');
            $table->foreignId('unlocked_by')->nullable()->after('unlock_reason')->constrained('users')->nullOnDelete();
        });

        Schema::create('versement_bancaire_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('versement_bancaire_id')->constrained('versement_bancaires')->cascadeOnDelete();
            $table->foreignId('modified_by')->constrained('users')->restrictOnDelete();
            $table->json('before');
            $table->json('after');
            $table->string('comment', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('versement_bancaire_histories');

        Schema::table('versement_bancaires', function (Blueprint $table) {
            $table->dropConstrainedForeignId('unlocked_by');
            $table->dropColumn([
                'receipt_date',
                'force_unlocked_until',
                'unlock_reason',
            ]);
        });
    }
};
