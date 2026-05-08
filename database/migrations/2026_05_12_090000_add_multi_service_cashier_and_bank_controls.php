<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('user_service_modules')) {
            Schema::create('user_service_modules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('module', 30);
                $table->timestamps();

                $table->unique(['user_id', 'module']);
                $table->index('module');
            });
        }

        Schema::table('gares', function (Blueprint $table) {
            if (! Schema::hasColumn('gares', 'versement_mode')) {
                $table->string('versement_mode', 20)->default('direct')->after('address');
                $table->index('versement_mode');
            }

            if (! Schema::hasColumn('gares', 'cashier_user_id')) {
                $table->foreignId('cashier_user_id')->nullable()->after('versement_mode')->constrained('users')->nullOnDelete();
                $table->index('cashier_user_id');
            }

            if (! Schema::hasColumn('gares', 'is_virtual')) {
                $table->boolean('is_virtual')->default(false)->after('cashier_user_id');
                $table->index('is_virtual');
            }

            if (! Schema::hasColumn('gares', 'virtual_owner_user_id')) {
                $table->foreignId('virtual_owner_user_id')->nullable()->after('is_virtual')->constrained('users')->nullOnDelete();
                $table->index('virtual_owner_user_id');
            }

            if (! Schema::hasColumn('gares', 'virtual_scope')) {
                $table->string('virtual_scope', 30)->nullable()->after('virtual_owner_user_id');
                $table->index('virtual_scope');
            }
        });

        Schema::table('versement_bancaires', function (Blueprint $table) {
            if (! Schema::hasColumn('versement_bancaires', 'account_type')) {
                $table->string('account_type', 20)->default('national')->after('amount');
                $table->index('account_type');
            }
        });

        if (! Schema::hasTable('cashier_receipt_confirmations')) {
            Schema::create('cashier_receipt_confirmations', function (Blueprint $table) {
                $table->id();
                $table->string('service_scope', 30)->default('gares');
                $table->foreignId('gare_id')->constrained('gares')->cascadeOnDelete();
                $table->foreignId('cashier_id')->constrained('users')->cascadeOnDelete();
                $table->date('operation_date');
                $table->decimal('expected_total', 14, 2)->default(0);
                $table->decimal('expected_inter_total', 14, 2)->default(0);
                $table->decimal('expected_national_total', 14, 2)->default(0);
                $table->decimal('received_total', 14, 2)->default(0);
                $table->decimal('received_inter_total', 14, 2)->default(0);
                $table->decimal('received_national_total', 14, 2)->default(0);
                $table->boolean('is_verified')->default(false);
                $table->timestamp('verified_at')->nullable();
                $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('note')->nullable();
                $table->timestamps();

                $table->unique(
                    ['service_scope', 'gare_id', 'cashier_id', 'operation_date'],
                    'cashier_receipts_scope_gare_cashier_date_unique'
                );
                $table->index(['service_scope', 'cashier_id', 'operation_date'], 'cashier_receipts_scope_cashier_date_index');
            });
        }

        Schema::table('verification_checks', function (Blueprint $table) {
            if (! Schema::hasColumn('verification_checks', 'recettes_inter_total')) {
                $table->decimal('recettes_inter_total', 14, 2)->default(0)->after('recettes_total');
            }

            if (! Schema::hasColumn('verification_checks', 'recettes_national_total')) {
                $table->decimal('recettes_national_total', 14, 2)->default(0)->after('recettes_inter_total');
            }

            if (! Schema::hasColumn('verification_checks', 'depenses_inter_total')) {
                $table->decimal('depenses_inter_total', 14, 2)->default(0)->after('depenses_total');
            }

            if (! Schema::hasColumn('verification_checks', 'depenses_national_total')) {
                $table->decimal('depenses_national_total', 14, 2)->default(0)->after('depenses_inter_total');
            }

            if (! Schema::hasColumn('verification_checks', 'versements_inter_total')) {
                $table->decimal('versements_inter_total', 14, 2)->default(0)->after('versements_total');
            }

            if (! Schema::hasColumn('verification_checks', 'versements_national_total')) {
                $table->decimal('versements_national_total', 14, 2)->default(0)->after('versements_inter_total');
            }

            if (! Schema::hasColumn('verification_checks', 'expected_inter_versement')) {
                $table->decimal('expected_inter_versement', 14, 2)->default(0)->after('expected_versement');
            }

            if (! Schema::hasColumn('verification_checks', 'expected_national_versement')) {
                $table->decimal('expected_national_versement', 14, 2)->default(0)->after('expected_inter_versement');
            }

            if (! Schema::hasColumn('verification_checks', 'difference_inter')) {
                $table->decimal('difference_inter', 14, 2)->default(0)->after('difference');
            }

            if (! Schema::hasColumn('verification_checks', 'difference_national')) {
                $table->decimal('difference_national', 14, 2)->default(0)->after('difference_inter');
            }

            if (! Schema::hasColumn('verification_checks', 'control_mode')) {
                $table->string('control_mode', 30)->default('direct')->after('status');
                $table->index('control_mode');
            }
        });
    }

    public function down(): void
    {
        Schema::table('verification_checks', function (Blueprint $table) {
            foreach ([
                'recettes_inter_total',
                'recettes_national_total',
                'depenses_inter_total',
                'depenses_national_total',
                'versements_inter_total',
                'versements_national_total',
                'expected_inter_versement',
                'expected_national_versement',
                'difference_inter',
                'difference_national',
            ] as $column) {
                if (Schema::hasColumn('verification_checks', $column)) {
                    $table->dropColumn($column);
                }
            }

            if (Schema::hasColumn('verification_checks', 'control_mode')) {
                try {
                    $table->dropIndex('verification_checks_control_mode_index');
                } catch (\Throwable $e) {
                }
                $table->dropColumn('control_mode');
            }
        });

        Schema::dropIfExists('cashier_receipt_confirmations');

        Schema::table('versement_bancaires', function (Blueprint $table) {
            if (Schema::hasColumn('versement_bancaires', 'account_type')) {
                try {
                    $table->dropIndex('versement_bancaires_account_type_index');
                } catch (\Throwable $e) {
                }
                $table->dropColumn('account_type');
            }
        });

        Schema::table('gares', function (Blueprint $table) {
            if (Schema::hasColumn('gares', 'virtual_scope')) {
                try {
                    $table->dropIndex('gares_virtual_scope_index');
                } catch (\Throwable $e) {
                }
                $table->dropColumn('virtual_scope');
            }

            if (Schema::hasColumn('gares', 'virtual_owner_user_id')) {
                try {
                    $table->dropIndex('gares_virtual_owner_user_id_index');
                } catch (\Throwable $e) {
                }
                $table->dropConstrainedForeignId('virtual_owner_user_id');
            }

            if (Schema::hasColumn('gares', 'is_virtual')) {
                try {
                    $table->dropIndex('gares_is_virtual_index');
                } catch (\Throwable $e) {
                }
                $table->dropColumn('is_virtual');
            }

            if (Schema::hasColumn('gares', 'cashier_user_id')) {
                try {
                    $table->dropIndex('gares_cashier_user_id_index');
                } catch (\Throwable $e) {
                }
                $table->dropConstrainedForeignId('cashier_user_id');
            }

            if (Schema::hasColumn('gares', 'versement_mode')) {
                try {
                    $table->dropIndex('gares_versement_mode_index');
                } catch (\Throwable $e) {
                }
                $table->dropColumn('versement_mode');
            }
        });

        Schema::dropIfExists('user_service_modules');
    }
};

