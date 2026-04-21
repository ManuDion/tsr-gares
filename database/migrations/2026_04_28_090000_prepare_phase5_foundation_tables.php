<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('gare_id')->constrained('departments')->nullOnDelete();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_code', 60)->unique();
            $table->string('first_name', 120);
            $table->string('last_name', 120);
            $table->string('full_name', 255)->nullable();
            $table->string('phone', 60)->nullable();
            $table->string('email')->nullable();
            $table->string('job_title', 150)->nullable();
            $table->date('hire_date')->nullable();
            $table->string('employment_status', 40)->default('active');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('gare_id')->nullable()->constrained('gares')->nullOnDelete();
            $table->boolean('mobile_app_enabled')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['department_id', 'gare_id']);
        });

        Schema::create('courriers', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 100)->nullable()->unique();
            $table->string('subject', 255);
            $table->string('direction', 30)->default('internal');
            $table->string('priority', 30)->default('normal');
            $table->string('status', 40)->default('draft');
            $table->foreignId('origin_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('destination_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('gare_id')->nullable()->constrained('gares')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['direction', 'status']);
        });

        Schema::create('workflow_transfers', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('subject');
            $table->string('reference', 100)->nullable();
            $table->string('status', 40)->default('pending');
            $table->foreignId('origin_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('destination_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('transferred_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('transferred_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'destination_department_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_transfers');
        Schema::dropIfExists('courriers');
        Schema::dropIfExists('employees');

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
        });

        Schema::dropIfExists('departments');
    }
};
