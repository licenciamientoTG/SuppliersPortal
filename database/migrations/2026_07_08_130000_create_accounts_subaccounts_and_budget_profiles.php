<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legacy_expense_category_id')->nullable()->unique();
            $table->string('code', 50)->unique();
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->string('account_category', 120)->nullable();
            $table->boolean('is_fixed_asset')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->noActionOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->noActionOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->noActionOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['is_active', 'deleted_at']);
        });

        Schema::create('subaccounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legacy_budget_cedula_id')->nullable()->unique();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnUpdate()->noActionOnDelete();
            $table->string('name', 200);
            $table->string('subaccount_category', 120)->nullable();
            $table->boolean('is_fixed_asset')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->noActionOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->noActionOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->noActionOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index('account_id');
            $table->index(['is_active', 'deleted_at']);
            $table->unique(['account_id', 'name']);
        });

        Schema::create('account_product_service', function (Blueprint $table) {
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('product_service_id')->constrained('products_services')->cascadeOnDelete();
            $table->primary(['account_id', 'product_service_id']);
        });

        Schema::create('product_service_subaccount', function (Blueprint $table) {
            $table->foreignId('product_service_id')->constrained('products_services')->cascadeOnDelete();
            $table->foreignId('subaccount_id')->constrained('subaccounts')->cascadeOnDelete();
            $table->primary(['product_service_id', 'subaccount_id']);
        });

        Schema::create('budget_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('key', 80)->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('budget_profile_subaccount', function (Blueprint $table) {
            $table->foreignId('budget_profile_id')->constrained('budget_profiles')->cascadeOnDelete();
            $table->foreignId('subaccount_id')->constrained('subaccounts')->cascadeOnDelete();
            $table->primary(['budget_profile_id', 'subaccount_id']);
        });

        Schema::create('employee_position_budget_profile', function (Blueprint $table) {
            $table->id();
            $table->string('raw_job_title', 180)->unique();
            $table->string('normalized_job_title', 180)->index();
            $table->foreignId('budget_profile_id')->nullable()->constrained('budget_profiles')->nullOnDelete();
            $table->boolean('is_excluded')->default(false);
            $table->unsignedInteger('employees_count')->default(0);
            $table->timestamps();

            $table->index(['budget_profile_id', 'is_excluded']);
        });

        Schema::create('department_subaccount', function (Blueprint $table) {
            $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete();
            $table->foreignId('subaccount_id')->constrained('subaccounts')->cascadeOnDelete();
            $table->primary(['department_id', 'subaccount_id']);
        });

        Schema::create('subaccount_user', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('subaccount_id')->constrained('subaccounts')->cascadeOnDelete();
            $table->primary(['user_id', 'subaccount_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('job_title')
                ->constrained('departments')->nullOnDelete();
            $table->foreignId('budget_profile_id')->nullable()->after('department_id')
                ->constrained('budget_profiles')->nullOnDelete();
            $table->index(['department_id', 'budget_profile_id']);
        });

        Schema::table('requisition_items', function (Blueprint $table) {
            if (! Schema::hasColumn('requisition_items', 'es_inventariable')) {
                $table->boolean('es_inventariable')->default(false)->after('notes');
            }
        });

        $this->copyLegacyAccounts();
        $this->copyLegacyProductLinks();
    }

    public function down(): void
    {
        Schema::table('requisition_items', function (Blueprint $table) {
            if (Schema::hasColumn('requisition_items', 'es_inventariable')) {
                $table->dropColumn('es_inventariable');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'budget_profile_id')) {
                $table->dropForeign(['budget_profile_id']);
            }
            if (Schema::hasColumn('users', 'department_id')) {
                $table->dropForeign(['department_id']);
            }
            $table->dropIndex(['department_id', 'budget_profile_id']);
            $table->dropColumn(['department_id', 'budget_profile_id']);
        });

        Schema::dropIfExists('subaccount_user');
        Schema::dropIfExists('department_subaccount');
        Schema::dropIfExists('employee_position_budget_profile');
        Schema::dropIfExists('budget_profile_subaccount');
        Schema::dropIfExists('budget_profiles');
        Schema::dropIfExists('product_service_subaccount');
        Schema::dropIfExists('account_product_service');
        Schema::dropIfExists('subaccounts');
        Schema::dropIfExists('accounts');
    }

    private function copyLegacyAccounts(): void
    {
        if (! Schema::hasTable('expense_categories') || ! Schema::hasTable('budget_cedulas')) {
            return;
        }

        $now = now();

        DB::table('expense_categories')->orderBy('id')->get()->each(function ($row) use ($now) {
            DB::table('accounts')->updateOrInsert(
                ['legacy_expense_category_id' => $row->id],
                [
                    'code' => $row->code,
                    'name' => $row->name,
                    'description' => $row->description,
                    'account_category' => null,
                    'is_fixed_asset' => false,
                    'is_active' => ($row->status ?? 'ACTIVO') === 'ACTIVO',
                    'created_by' => $row->created_by ?? null,
                    'updated_by' => $row->updated_by ?? null,
                    'deleted_by' => $row->deleted_by ?? null,
                    'deleted_at' => $row->deleted_at ?? null,
                    'created_at' => $row->created_at ?? $now,
                    'updated_at' => $row->updated_at ?? $now,
                ]
            );
        });

        DB::table('budget_cedulas')->orderBy('id')->get()->each(function ($row) use ($now) {
            $accountId = DB::table('accounts')
                ->where('legacy_expense_category_id', $row->expense_category_id)
                ->value('id');

            if (! $accountId) {
                return;
            }

            DB::table('subaccounts')->updateOrInsert(
                ['legacy_budget_cedula_id' => $row->id],
                [
                    'account_id' => $accountId,
                    'name' => $row->name,
                    'subaccount_category' => null,
                    'is_fixed_asset' => false,
                    'is_active' => ($row->status ?? 'ACTIVO') === 'ACTIVO',
                    'created_by' => $row->created_by ?? null,
                    'updated_by' => $row->updated_by ?? null,
                    'deleted_by' => $row->deleted_by ?? null,
                    'deleted_at' => $row->deleted_at ?? null,
                    'created_at' => $row->created_at ?? $now,
                    'updated_at' => $row->updated_at ?? $now,
                ]
            );
        });
    }

    private function copyLegacyProductLinks(): void
    {
        if (Schema::hasTable('expense_category_product_service')) {
            DB::table('expense_category_product_service')->orderBy('product_service_id')->get()->each(function ($row) {
                $accountId = DB::table('accounts')
                    ->where('legacy_expense_category_id', $row->expense_category_id)
                    ->value('id');

                if (! $accountId) {
                    return;
                }

                DB::table('account_product_service')->updateOrInsert([
                    'account_id' => $accountId,
                    'product_service_id' => $row->product_service_id,
                ]);
            });
        }

        if (Schema::hasTable('budget_cedula_product_service')) {
            DB::table('budget_cedula_product_service')->orderBy('product_service_id')->get()->each(function ($row) {
                $subaccountId = DB::table('subaccounts')
                    ->where('legacy_budget_cedula_id', $row->budget_cedula_id)
                    ->value('id');

                if (! $subaccountId) {
                    return;
                }

                DB::table('product_service_subaccount')->updateOrInsert([
                    'product_service_id' => $row->product_service_id,
                    'subaccount_id' => $subaccountId,
                ]);
            });
        }
    }
};
