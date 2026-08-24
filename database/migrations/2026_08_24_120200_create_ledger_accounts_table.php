<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('one_goal_id')->unique();
            $table->foreignId('parent_id')->nullable()->constrained('ledger_accounts');
            $table->unsignedInteger('one_goal_parent_id')->default(0);
            $table->string('code', 30)->nullable()->index();
            $table->string('name', 255);
            $table->string('alternate_name', 255)->nullable();
            $table->unsignedTinyInteger('nature')->default(0);
            $table->unsignedTinyInteger('account_level')->default(0);
            $table->unsignedInteger('one_goal_type_id')->default(0);
            $table->string('one_goal_external_system_id', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_selectable')->default(true);
            $table->timestamps();

            $table->index(['parent_id', 'account_level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_accounts');
    }
};
