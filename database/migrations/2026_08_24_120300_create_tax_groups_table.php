<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_groups', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('one_goal_id')->unique();
            $table->string('name', 150);
            $table->unsignedInteger('one_goal_type_id')->default(0);
            $table->unsignedInteger('one_goal_compound_id')->default(0);
            $table->boolean('is_payment_tax')->default(false);
            $table->boolean('is_border_zone')->default(false);
            $table->boolean('is_vat_tax')->default(false);
            $table->string('sat_tax_object', 2)->nullable();
            $table->boolean('is_south_border_zone')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('tax_group_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('one_goal_id')->unique();
            $table->foreignId('tax_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tax_code_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ledger_account_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('one_goal_tax_code_id')->default(0);
            $table->unsignedInteger('one_goal_ledger_account_id')->default(0);
            $table->boolean('is_iva_base')->default(false);
            $table->unsignedInteger('related_iva_item_one_goal_id')->default(0);
            $table->unsignedInteger('withholding_type_id')->default(0);
            $table->boolean('is_excluded_from_cfdi')->default(false);
            $table->string('sat_tax_object', 2)->nullable();
            $table->timestamps();

            $table->index('tax_group_id');
            $table->index('tax_code_id');
            $table->index('ledger_account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_group_items');
        Schema::dropIfExists('tax_groups');
    }
};
