<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('cost_centers', function (Blueprint $table) {
            $table->string('cost_center_type', 20)->default('STANDARD')->after('responsible_user_id');
            $table->index('cost_center_type');
        });

        Schema::create('cost_center_distributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('distribution_cost_center_id')->constrained('cost_centers')->cascadeOnDelete();
            $table->foreignId('target_cost_center_id')->constrained('cost_centers')->noActionOnDelete();
            $table->decimal('percentage', 7, 4);
            $table->timestamps();
            $table->unique(['distribution_cost_center_id', 'target_cost_center_id'], 'cc_distribution_target_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cost_center_distributions');
        Schema::table('cost_centers', function (Blueprint $table) {
            $table->dropIndex(['cost_center_type']);
            $table->dropColumn('cost_center_type');
        });
    }
};
