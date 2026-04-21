<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Throwable;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'phone')) {
                $table->string('phone', 40)->nullable()->after('name');
            }
            if (! Schema::hasColumn('users', 'must_change_password')) {
                $table->boolean('must_change_password')->default(false)->after('is_active');
            }
        });

        foreach (['recettes', 'depenses', 'versement_bancaires', 'verification_checks', 'daily_controls'] as $tableName) {
            if (Schema::hasTable($tableName) && ! Schema::hasColumn($tableName, 'service_scope')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->string('service_scope', 30)->default('gares')->after('id');
                    $table->index('service_scope');
                });
            }
        }


        if (Schema::hasTable('verification_checks')) {
            Schema::table('verification_checks', function (Blueprint $table) {
                try {
                    $table->dropUnique('verification_checks_gare_id_operation_date_unique');
                } catch (Throwable $e) {
                }

                try {
                    $table->unique(['service_scope', 'gare_id', 'operation_date'], 'verification_checks_scope_gare_operation_unique');
                } catch (Throwable $e) {
                }
            });
        }

        if (Schema::hasTable('daily_controls')) {
            Schema::table('daily_controls', function (Blueprint $table) {
                try {
                    $table->dropUnique('daily_controls_gare_id_concerned_date_unique');
                } catch (Throwable $e) {
                }

                try {
                    $table->unique(['service_scope', 'gare_id', 'concerned_date'], 'daily_controls_scope_gare_concerned_unique');
                } catch (Throwable $e) {
                }
            });
        }

        if (! Schema::hasTable('employee_assignments')) {
            Schema::create('employee_assignments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
                $table->foreignId('gare_id')->nullable()->constrained('gares')->nullOnDelete();
                $table->string('job_title', 150)->nullable();
                $table->date('assigned_at');
                $table->date('ended_at')->nullable();
                $table->string('decision_reference', 120)->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('employee_documents')) {
            Schema::create('employee_documents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->string('document_type', 120);
                $table->string('label', 180)->nullable();
                $table->string('original_name');
                $table->string('file_name');
                $table->string('mime_type', 100)->nullable();
                $table->unsignedBigInteger('size')->default(0);
                $table->string('disk', 50)->default('private');
                $table->string('path');
                $table->date('expires_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['employee_id', 'document_type']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_documents');
        Schema::dropIfExists('employee_assignments');

        if (Schema::hasTable('verification_checks')) {
            Schema::table('verification_checks', function (Blueprint $table) {
                try {
                    $table->dropUnique('verification_checks_scope_gare_operation_unique');
                } catch (Throwable $e) {
                }

                try {
                    $table->dropUnique('verification_checks_gare_id_operation_date_unique');
                } catch (Throwable $e) {
                }

                try {
                    $table->unique(['gare_id', 'operation_date'], 'verification_checks_gare_id_operation_date_unique');
                } catch (Throwable $e) {
                }

                try {
                    $table->dropIndex('verification_checks_service_scope_index');
                } catch (Throwable $e) {
                }

                if (Schema::hasColumn('verification_checks', 'service_scope')) {
                    $table->dropColumn('service_scope');
                }
            });
        }

        if (Schema::hasTable('daily_controls')) {
            Schema::table('daily_controls', function (Blueprint $table) {
                try {
                    $table->dropUnique('daily_controls_scope_gare_concerned_unique');
                } catch (Throwable $e) {
                }

                try {
                    $table->dropUnique('daily_controls_gare_id_concerned_date_unique');
                } catch (Throwable $e) {
                }

                try {
                    $table->unique(['gare_id', 'concerned_date'], 'daily_controls_gare_id_concerned_date_unique');
                } catch (Throwable $e) {
                }

                try {
                    $table->dropIndex('daily_controls_service_scope_index');
                } catch (Throwable $e) {
                }

                if (Schema::hasColumn('daily_controls', 'service_scope')) {
                    $table->dropColumn('service_scope');
                }
            });
        }

        foreach (['versement_bancaires', 'depenses', 'recettes'] as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'service_scope')) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    try {
                        $table->dropIndex($tableName.'_service_scope_index');
                    } catch (Throwable $e) {
                    }

                    $table->dropColumn('service_scope');
                });
            }
        }

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'must_change_password')) {
                $table->dropColumn('must_change_password');
            }
            if (Schema::hasColumn('users', 'phone')) {
                $table->dropColumn('phone');
            }
        });
    }
};

