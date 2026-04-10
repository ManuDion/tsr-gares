<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('recettes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gare_id')->constrained('gares')->cascadeOnDelete();
            $table->date('operation_date');
            $table->decimal('amount', 14, 2);
            $table->string('reference', 100)->nullable();
            $table->text('description')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('force_unlocked_until')->nullable();
            $table->text('unlock_reason')->nullable();
            $table->foreignId('unlocked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['gare_id', 'operation_date']);
        });

        Schema::create('depenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gare_id')->constrained('gares')->cascadeOnDelete();
            $table->date('operation_date');
            $table->decimal('amount', 14, 2);
            $table->string('motif', 150);
            $table->string('reference', 100)->nullable();
            $table->text('description')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['gare_id', 'operation_date']);
        });

        Schema::create('versement_bancaires', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gare_id')->constrained('gares')->cascadeOnDelete();
            $table->date('operation_date');
            $table->decimal('amount', 14, 2);
            $table->string('reference', 100)->nullable();
            $table->string('bank_name', 150)->nullable();
            $table->text('description')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['gare_id', 'operation_date']);
        });

        Schema::create('recette_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recette_id')->constrained('recettes')->cascadeOnDelete();
            $table->foreignId('modified_by')->constrained('users')->restrictOnDelete();
            $table->json('before');
            $table->json('after');
            $table->string('comment', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('piece_justificatives', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('attachable');
            $table->string('document_type', 80);
            $table->string('original_name');
            $table->string('file_name');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('disk', 50)->default('private');
            $table->string('path');
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('uploaded_at');
            $table->timestamps();
        });

        Schema::create('document_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('piece_justificative_id')->constrained('piece_justificatives')->cascadeOnDelete();
            $table->string('status', 40)->default('pending');
            $table->string('provider', 60)->nullable();
            $table->json('extracted_data')->nullable();
            $table->json('confidence')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();
        });

        Schema::create('daily_controls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gare_id')->constrained('gares')->cascadeOnDelete();
            $table->date('control_date');
            $table->date('concerned_date');
            $table->boolean('has_recette')->default(false);
            $table->boolean('has_depense')->default(false);
            $table->boolean('has_versement')->default(false);
            $table->boolean('is_compliant')->default(false);
            $table->json('missing_operations')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['gare_id', 'concerned_date']);
        });

        Schema::create('notification_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 80);
            $table->string('subject', 255);
            $table->text('content')->nullable();
            $table->string('status', 40)->default('generated');
            $table->date('control_date')->nullable();
            $table->date('concerned_date')->nullable();
            $table->json('gares')->nullable();
            $table->json('operations')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('notification_histories');
        Schema::dropIfExists('daily_controls');
        Schema::dropIfExists('document_analyses');
        Schema::dropIfExists('piece_justificatives');
        Schema::dropIfExists('recette_histories');
        Schema::dropIfExists('versement_bancaires');
        Schema::dropIfExists('depenses');
        Schema::dropIfExists('recettes');
    }
};
