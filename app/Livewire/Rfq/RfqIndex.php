<?php

namespace App\Livewire\Rfq;

use App\Enum\RequisitionStatus;
use App\Models\Requisition;
use App\Services\Rfq\RfqBlockStatusService;
use Livewire\Component;
use Livewire\WithPagination;

class RfqIndex extends Component
{
    use WithPagination;

    public $search = '';
    public $statusFilter = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => ''],
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }

    public function render()
    {
        // Status permitidos para cotizar
        $allowedStatuses = [
            RequisitionStatus::PENDING,
            RequisitionStatus::PAUSED,
            RequisitionStatus::APPROVED,
            RequisitionStatus::IN_QUOTATION,
            RequisitionStatus::QUOTED,
            RequisitionStatus::PENDING_BUDGET_ADJUSTMENT,
        ];

        $requisitions = Requisition::query()
            ->with(['requester', 'company', 'department', 'items.costCenter', 'rfqs'])
            ->whereIn('status', $allowedStatuses)
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('folio', 'like', '%' . $this->search . '%')
                        ->orWhereHas('requester', function ($q2) {
                            $q2->where('name', 'like', '%' . $this->search . '%');
                        })
                        ->orWhereHas('items.costCenter', function ($q2) {
                            $q2->where('name', 'like', '%' . $this->search . '%')
                                ->orWhere('code', 'like', '%' . $this->search . '%');
                        });
                });
            })
            ->when($this->statusFilter, function ($query) {
                $query->where('status', $this->statusFilter);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        $blockedSummaries = $requisitions->getCollection()
            ->mapWithKeys(fn (Requisition $requisition) => [
                $requisition->id => app(RfqBlockStatusService::class)->requisitionSummary($requisition->rfqs),
            ]);

        return view('livewire.rfq.rfq-index', [
            'requisitions' => $requisitions,
            'allowedStatuses' => $allowedStatuses,
            'blockedSummaries' => $blockedSummaries,
        ]); // ← SOLO ESTO, sin ->layout() ni ->section()
    }
}
