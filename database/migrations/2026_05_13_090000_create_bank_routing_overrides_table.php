<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_routing_overrides', function (Blueprint $table) {
            $table->id();
            $table->string('service_scope', 20)->default('gares')->index();
            $table->string('forced_account_type', 20);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->string('notes', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['service_scope', 'start_date', 'end_date'], 'bank_routing_scope_period_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_routing_overrides');
    }
};
