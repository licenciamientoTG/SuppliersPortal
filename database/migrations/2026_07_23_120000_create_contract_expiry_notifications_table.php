<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_expiry_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->integer('milestone_days');
            $table->timestamps();

            $table->unique(['contract_id', 'milestone_days'], 'contract_expiry_notice_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_expiry_notifications');
    }
};
