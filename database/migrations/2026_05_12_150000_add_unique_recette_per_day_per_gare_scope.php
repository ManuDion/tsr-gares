<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    protected string $uniqueName = 'recettes_scope_gare_operation_unique';

    public function up(): void
    {
        if (! Schema::hasTable('recettes')) {
            return;
        }

        if ($this->hasUniqueIndex($this->uniqueName)) {
            return;
        }

        $duplicates = DB::table('recettes')
            ->selectRaw('service_scope, gare_id, operation_date, COUNT(*) as total')
            ->groupBy('service_scope', 'gare_id', 'operation_date')
            ->havingRaw('COUNT(*) > 1')
            ->limit(5)
            ->get();

        if ($duplicates->isNotEmpty()) {
            $samples = $duplicates
                ->map(fn ($row) => sprintf(
                    '[scope=%s, gare_id=%s, date=%s, total=%s]',
                    (string) $row->service_scope,
                    (string) $row->gare_id,
                    (string) $row->operation_date,
                    (string) $row->total
                ))
                ->implode(', ');

            throw new RuntimeException(
                "Migration bloquee: des recettes en doublon existent deja pour une meme gare/date/service. ".
                "Exemples: {$samples}. Nettoyez ces doublons avant de relancer la migration."
            );
        }

        Schema::table('recettes', function (Blueprint $table) {
            $table->unique(['service_scope', 'gare_id', 'operation_date'], $this->uniqueName);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('recettes') || ! $this->hasUniqueIndex($this->uniqueName)) {
            return;
        }

        Schema::table('recettes', function (Blueprint $table) {
            $table->dropUnique($this->uniqueName);
        });
    }

    protected function hasUniqueIndex(string $indexName): bool
    {
        $database = DB::getDatabaseName();
        $table = DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', 'recettes')
            ->where('index_name', $indexName)
            ->where('non_unique', 0)
            ->exists();

        return (bool) $table;
    }
};

