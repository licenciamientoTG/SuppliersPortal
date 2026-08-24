<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_codes', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('one_goal_id')->unique();
            $table->string('name', 100);
            // Aldo indicó conservar el signo original de One Goal: las retenciones son negativas.
            $table->decimal('rate', 12, 4);
            $table->enum('calculation_type', ['percentage', 'fixed_quota']);
            $table->unsignedInteger('one_goal_tax_type_id')->default(0);
            $table->boolean('is_vat')->default(false);
            $table->boolean('is_withholding')->default(false);
            $table->boolean('is_exempt')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_selectable')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_codes');
    }
};
