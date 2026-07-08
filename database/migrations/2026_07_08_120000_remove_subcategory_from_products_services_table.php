<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('products_services', function (Blueprint $table) {
            $table->dropIndex(['category_id', 'subcategory']);
            $table->dropColumn('subcategory');
        });
    }

    public function down(): void
    {
        Schema::table('products_services', function (Blueprint $table) {
            $table->string('subcategory', 100)->nullable()->after('category_id')
                ->comment('Subcategoría específica');
            $table->index(['category_id', 'subcategory']);
        });
    }
};
