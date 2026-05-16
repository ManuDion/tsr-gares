<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    protected string $uniqueName = 'versements_scope_gare_operation_account_unique';

    public function up(): void
    {
        if (! Schema::hasTable('versement_bancaires')) {
            return;
        }

        if ($this->hasUniqueIndex($this->uniqueName)) {
            return;
        }

        $duplicates = DB::table('versement_bancaires')
            ->selectRaw('service_scope, gare_id, operation_date, account_type, COUNT(*) as total')
            ->groupBy('service_scope', 'gare_id', 'operation_date', 'account_type')
            ->havingRaw('COUNT(*) > 1')
            ->limit(5)
            ->get();

        if ($duplicates->isNotEmpty()) {
            $samples = $duplicates
                ->map(fn ($row) => sprintf(
                    '[scope=%s, gare_id=%s, date=%s, compte=%s, total=%s]',
                    (string) $row->service_scope,
                    (string) $row->gare_id,
                    (string) $row->operation_date,
                    (string) $row->account_type,
                    (string) $row->total
                ))
                ->implode(', ');

            throw new RuntimeException(
                "Migration bloquee: des versements en doublon existent deja pour une meme gare/date/compte/service. ".
                "Exemples: {$samples}. Nettoyez ces doublons avant de relancer la migration."
            );
        }

        Schema::table('versement_bancaires', function (Blueprint $table) {
            $table->unique(
                ['service_scope', 'gare_id', 'operation_date', 'account_type'],
                $this->uniqueName
            );
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('versement_bancaires') || ! $this->hasUniqueIndex($this->uniqueName)) {
            return;
        }

        Schema::table('versement_bancaires', function (Blueprint $table) {
            $table->dropUnique($this->uniqueName);
        });
    }

    protected function hasUniqueIndex(string $indexName): bool
    {
        $database = DB::getDatabaseName();

        return DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', 'versement_bancaires')
            ->where('index_name', $indexName)
            ->where('non_unique', 0)
            ->exists();
    }
};
