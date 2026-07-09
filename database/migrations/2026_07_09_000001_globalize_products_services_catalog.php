<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('products_services', 'company_id')) {
            $this->dropIndexIfExists('idx_company_cc_active');
            $this->dropIndexIfExists('products_services_company_id_index');
            $this->dropForeignIfExists('company_id');

            Schema::table('products_services', function (Blueprint $table): void {
                $table->dropColumn('company_id');
            });
        }

        if (Schema::hasColumn('products_services', 'cost_center_id')) {
            $this->dropIndexIfExists('products_services_cost_center_id_index');
            $this->dropForeignIfExists('cost_center_id');

            Schema::table('products_services', function (Blueprint $table): void {
                $table->dropColumn('cost_center_id');
            });
        }

        if (Schema::hasColumn('products_services', 'category_id')) {
            $this->dropIndexIfExists('products_services_category_id_subcategory_index');
            $this->dropForeignIfExists('category_id');

            Schema::table('products_services', function (Blueprint $table): void {
                $table->dropColumn('category_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('products_services', function (Blueprint $table): void {
            if (! Schema::hasColumn('products_services', 'category_id')) {
                $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            }

            if (! Schema::hasColumn('products_services', 'cost_center_id')) {
                $table->foreignId('cost_center_id')->nullable()->constrained('cost_centers')->nullOnDelete();
            }

            if (! Schema::hasColumn('products_services', 'company_id')) {
                $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            }
        });

        $this->addIndexIfPossible('products_services_company_id_index', ['company_id']);
        $this->addIndexIfPossible('products_services_cost_center_id_index', ['cost_center_id']);
        $this->addIndexIfPossible('idx_company_cc_active', ['company_id', 'cost_center_id', 'is_active']);
    }

    private function dropIndexIfExists(string $index): void
    {
        try {
            Schema::table('products_services', function (Blueprint $table) use ($index): void {
                $table->dropIndex($index);
            });
        } catch (Throwable) {
            //
        }
    }

    private function dropForeignIfExists(string $column): void
    {
        try {
            Schema::table('products_services', function (Blueprint $table) use ($column): void {
                $table->dropForeign([$column]);
            });
        } catch (Throwable) {
            //
        }
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function addIndexIfPossible(string $index, array $columns): void
    {
        try {
            Schema::table('products_services', function (Blueprint $table) use ($index, $columns): void {
                $table->index($columns, $index);
            });
        } catch (Throwable) {
            //
        }
    }
};
