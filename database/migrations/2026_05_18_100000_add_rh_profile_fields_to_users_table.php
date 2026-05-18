<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'contract_type')) {
                $table->string('contract_type', 120)->nullable()->after('department_id');
            }
            if (! Schema::hasColumn('users', 'assignment_location')) {
                $table->string('assignment_location', 180)->nullable()->after('contract_type');
            }
            if (! Schema::hasColumn('users', 'hr_service')) {
                $table->string('hr_service', 120)->nullable()->after('assignment_location');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $columns = [];
            if (Schema::hasColumn('users', 'hr_service')) {
                $columns[] = 'hr_service';
            }
            if (Schema::hasColumn('users', 'assignment_location')) {
                $columns[] = 'assignment_location';
            }
            if (Schema::hasColumn('users', 'contract_type')) {
                $columns[] = 'contract_type';
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
