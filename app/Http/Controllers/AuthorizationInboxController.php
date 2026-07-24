<?php

namespace App\Http\Controllers;

use App\Services\AuthorizationInboxService;
use Illuminate\Http\Request;

class AuthorizationInboxController extends Controller
{
    public function index(Request $request, AuthorizationInboxService $inbox)
    {
        abort_unless(
            $request->user()->hasRole('superadmin')
                || $request->user()->authorizerAssignment()->exists(),
            403,
            'Solo los autorizadores pueden consultar esta bandeja.'
        );

        return view('authorizations.index', [
            'items' => $inbox->itemsFor($request->user(), $request),
            'scope' => $request->string('scope')->toString() ?: 'all',
            'type' => $request->string('type')->toString() ?: 'all',
            'days' => $request->integer('days'),
        ]);
    }
}
