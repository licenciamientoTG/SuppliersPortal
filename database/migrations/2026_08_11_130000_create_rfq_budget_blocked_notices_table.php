<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rfq_budget_blocked_notices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rfq_id')->unique()->constrained('rfqs')->cascadeOnDelete();
            $table->foreignId('requisition_id')->constrained('requisitions')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->foreignId('buyer_user_id')->constrained('users')->cascadeOnDelete();
            $table->text('message');
            $table->text('note')->nullable();
            $table->timestamp('notified_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rfq_budget_blocked_notices');
    }
};
