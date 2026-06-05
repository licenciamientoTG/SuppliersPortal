<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requisition_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requisition_id')->constrained('requisitions')->cascadeOnDelete();
            $table->foreignId('buyer_user_id')->constrained('users')->cascadeOnDelete();
            $table->text('message');
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->index(['requisition_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requisition_feedback');
    }
};
