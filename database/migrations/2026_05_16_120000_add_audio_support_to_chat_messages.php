<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            if (! Schema::hasColumn('chat_messages', 'message_type')) {
                $table->string('message_type', 20)->default('text')->after('content');
                $table->index('message_type');
            }

            if (! Schema::hasColumn('chat_messages', 'audio_disk')) {
                $table->string('audio_disk', 50)->nullable()->after('message_type');
            }

            if (! Schema::hasColumn('chat_messages', 'audio_path')) {
                $table->string('audio_path')->nullable()->after('audio_disk');
            }

            if (! Schema::hasColumn('chat_messages', 'audio_mime_type')) {
                $table->string('audio_mime_type', 120)->nullable()->after('audio_path');
            }

            if (! Schema::hasColumn('chat_messages', 'audio_size')) {
                $table->unsignedBigInteger('audio_size')->nullable()->after('audio_mime_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            if (Schema::hasColumn('chat_messages', 'audio_size')) {
                $table->dropColumn('audio_size');
            }

            if (Schema::hasColumn('chat_messages', 'audio_mime_type')) {
                $table->dropColumn('audio_mime_type');
            }

            if (Schema::hasColumn('chat_messages', 'audio_path')) {
                $table->dropColumn('audio_path');
            }

            if (Schema::hasColumn('chat_messages', 'audio_disk')) {
                $table->dropColumn('audio_disk');
            }

            if (Schema::hasColumn('chat_messages', 'message_type')) {
                try {
                    $table->dropIndex('chat_messages_message_type_index');
                } catch (\Throwable $e) {
                }
                $table->dropColumn('message_type');
            }
        });
    }
};
