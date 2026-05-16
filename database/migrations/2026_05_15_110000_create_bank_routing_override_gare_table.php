<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_routing_override_gare', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_routing_override_id')
                ->constrained('bank_routing_overrides')
                ->cascadeOnDelete();
            $table->foreignId('gare_id')
                ->constrained('gares')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['bank_routing_override_id', 'gare_id'], 'br_override_gare_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_routing_override_gare');
    }
};

