<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('requisitions')
            ->where('status', 'QUOTED')
            ->whereExists(fn ($query) => $query
                ->selectRaw('1')
                ->from('quotation_summaries')
                ->whereColumn('quotation_summaries.requisition_id', 'requisitions.id')
                ->where('quotation_summaries.approval_status', 'pending'))
            ->update(['status' => 'IN_APPROVAL']);
    }

    public function down(): void
    {
        DB::table('requisitions')
            ->where('status', 'IN_APPROVAL')
            ->whereExists(fn ($query) => $query
                ->selectRaw('1')
                ->from('quotation_summaries')
                ->whereColumn('quotation_summaries.requisition_id', 'requisitions.id')
                ->where('quotation_summaries.approval_status', 'pending'))
            ->update(['status' => 'QUOTED']);
    }
};
