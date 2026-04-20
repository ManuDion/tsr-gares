<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('depenses', function (Blueprint $table) {
            if (! Schema::hasColumn('depenses', 'force_unlocked_until')) {
                $table->timestamp('force_unlocked_until')->nullable()->after('updated_by');
            }

            if (! Schema::hasColumn('depenses', 'unlock_reason')) {
                $table->text('unlock_reason')->nullable()->after('force_unlocked_until');
            }

            if (! Schema::hasColumn('depenses', 'unlocked_by')) {
                $table->foreignId('unlocked_by')->nullable()->after('unlock_reason')->constrained('users')->nullOnDelete();
            }
        });

        if (! Schema::hasTable('depense_histories')) {
            Schema::create('depense_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('depense_id')->constrained('depenses')->cascadeOnDelete();
                $table->foreignId('modified_by')->constrained('users')->restrictOnDelete();
                $table->json('before');
                $table->json('after');
                $table->string('comment', 255)->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('depense_histories');

        Schema::table('depenses', function (Blueprint $table) {
            if (Schema::hasColumn('depenses', 'unlocked_by')) {
                $table->dropConstrainedForeignId('unlocked_by');
            }
            if (Schema::hasColumn('depenses', 'unlock_reason')) {
                $table->dropColumn('unlock_reason');
            }
            if (Schema::hasColumn('depenses', 'force_unlocked_until')) {
                $table->dropColumn('force_unlocked_until');
            }
        });
    }
};
