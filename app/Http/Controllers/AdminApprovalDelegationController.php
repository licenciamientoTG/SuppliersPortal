<?php

namespace App\Http\Controllers;

use App\Models\ApprovalDelegation;
use App\Services\ApprovalDelegationService;
use Illuminate\Http\Request;

class AdminApprovalDelegationController extends Controller
{
    public function index()
    {
        return view('approval-delegations.admin', [
            'delegations' => ApprovalDelegation::query()
                ->effective()
                ->with(['delegator', 'activeMembers.delegate'])
                ->latest('starts_at')
                ->get(),
        ]);
    }

    public function deactivate(
        Request $request,
        ApprovalDelegation $delegation,
        ApprovalDelegationService $service
    ) {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        $service->deactivate($delegation, $request->user(), $validated['reason']);

        return back()->with('success', 'Delegación desactivada y motivo registrado.');
    }
}
