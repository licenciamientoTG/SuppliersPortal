<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReceptionsEvidenceReportRequest;
use App\Models\Company;
use App\Models\ReceivingLocation;
use App\Models\ReceptionAttachment;
use App\Models\User;
use App\Reports\Purchasing\ReceptionsEvidenceExport;
use App\Reports\Purchasing\ReceptionsEvidenceReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** RR-01 · Recepciones y su evidencia. */
class ReceptionsEvidenceReportController extends Controller
{
    public function __construct(private readonly ReceptionsEvidenceReport $report) {}

    public function index(Request $request)
    {
        $visibleCompanyIds = $this->report->visibleCompanyIds($request->user());

        $companies = Company::query()
            ->when($visibleCompanyIds !== null, fn ($q) => $q->whereIn('id', $visibleCompanyIds))
            ->orderBy('name')->get(['id', 'name']);
        $locations = ReceivingLocation::query()
            ->when($visibleCompanyIds !== null, fn ($q) => $q->whereIn('company_id', $visibleCompanyIds))
            ->orderBy('name')->get(['id', 'name', 'company_id']);
        $receivers = User::query()->orderBy('name')->get(['id', 'name']);

        return view('reports.receptions-evidence.index', compact('companies', 'locations', 'receivers'));
    }

    public function data(ReceptionsEvidenceReportRequest $request)
    {
        $result = $this->report->build($request->user(), $request->params());

        return response()->json($result);
    }

    public function export(ReceptionsEvidenceReportRequest $request, ReceptionsEvidenceExport $export, string $format)
    {
        $params = $request->params();
        $result = $export->download($request->user(), $params, $format);

        activity('reportes')->causedBy($request->user())
            ->withProperties(['format' => $format, 'filters' => $params, 'rows' => $result['rows']])
            ->log('Exportación RR-01 '.$format);

        return $result['response'];
    }

    /**
     * Verificación de hash bajo demanda: relee el archivo del disco y compara su SHA-256
     * contra el almacenado al momento de la carga (o del backfill).
     */
    public function verifyHash(ReceptionAttachment $attachment)
    {
        $disk = Storage::disk($attachment->disk ?: 'public');

        if (! $disk->exists($attachment->path)) {
            return response()->json([
                'matches' => false,
                'error' => 'El archivo ya no existe en el disco.',
            ], 404);
        }

        $recalculated = hash('sha256', $disk->get($attachment->path));

        return response()->json([
            'matches' => $recalculated === $attachment->sha256,
            'stored_sha256' => $attachment->sha256,
            'recalculated_sha256' => $recalculated,
        ]);
    }
}
