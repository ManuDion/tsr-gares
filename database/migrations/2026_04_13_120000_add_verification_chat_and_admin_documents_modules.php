<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('notification_histories', 'source_key')) {
            Schema::table('notification_histories', function (Blueprint $table) {
                $table->string('source_key', 190)->nullable()->after('type');
                $table->index('source_key');
            });
        }

        Schema::create('verification_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gare_id')->constrained('gares')->cascadeOnDelete();
            $table->date('operation_date');
            $table->decimal('recettes_total', 14, 2)->default(0);
            $table->decimal('depenses_total', 14, 2)->default(0);
            $table->decimal('versements_total', 14, 2)->default(0);
            $table->decimal('expected_versement', 14, 2)->default(0);
            $table->decimal('difference', 14, 2)->default(0);
            $table->string('status', 40)->default('pending');
            $table->timestamp('modifications_enabled_until')->nullable();
            $table->text('review_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['gare_id', 'operation_date']);
            $table->index(['operation_date', 'status']);
        });

        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->boolean('is_group')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
        });

        Schema::create('conversation_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('last_read_at')->nullable();
            $table->timestamps();

            $table->unique(['conversation_id', 'user_id']);
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->text('content');
            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
        });

        Schema::create('administrative_documents', function (Blueprint $table) {
            $table->id();
            $table->string('document_type', 120);
            $table->string('label', 180)->nullable();
            $table->string('original_name');
            $table->string('file_name');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('disk', 50)->default('private');
            $table->string('path');
            $table->date('expires_at');
            $table->boolean('is_active')->default(true);
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_renewed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['expires_at', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('administrative_documents');
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('conversation_user');
        Schema::dropIfExists('conversations');
        Schema::dropIfExists('verification_checks');

        if (Schema::hasColumn('notification_histories', 'source_key')) {
            Schema::table('notification_histories', function (Blueprint $table) {
                $table->dropIndex(['source_key']);
                $table->dropColumn('source_key');
            });
        }
    }
};
