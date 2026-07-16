<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterSupplierRequest;
use App\Models\Supplier;
use App\Models\SupplierDocument;
use App\Models\SupplierDocumentType;
use App\Models\User;
use App\Notifications\NewSupplierRegistrationForBuyerNotification;
use App\Notifications\SupplierWelcomeNotification;
use App\Rules\EfosNotListed;
use App\Rules\ValidRfc;
use App\Services\SupplierCsfExtractorService;
use App\Services\SupplierDocumentRequirementService;
use App\Support\SupplierFiscalCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use RuntimeException;
use Throwable;

class SupplierRegistrationController extends Controller
{
    public function create()
    {
        return view('auth.supplier-register');
    }

    public function parseCsf(Request $request, SupplierCsfExtractorService $extractor): JsonResponse
    {
        $validated = $request->validate([
            'csf' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ], [
            'csf.required' => 'Debes cargar la constancia de situacion fiscal en PDF.',
            'csf.mimes' => 'La constancia debe enviarse en formato PDF.',
            'csf.max' => 'La constancia fiscal no puede exceder 10 MB.',
        ]);

        try {
            $upload = $extractor->storeTemporaryUpload($validated['csf'], $request->session());
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        } catch (Throwable $exception) {
            Log::error('Error inesperado al procesar constancia fiscal.', [
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'No fue posible procesar la constancia fiscal en este momento. Intenta de nuevo o contacta a soporte.',
            ], 422);
        }

        return response()->json([
            'token' => $upload['token'],
            'data' => $upload['parsed'],
        ]);
    }

    public function store(RegisterSupplierRequest $request, SupplierCsfExtractorService $extractor, SupplierDocumentRequirementService $requirements)
    {
        $data = $request->validated();
        $isForeign = $request->boolean('is_foreign');

        return DB::transaction(function () use ($data, $request, $extractor, $isForeign, $requirements) {
            $repseData = $this->prepareRepseData($data);
            $csfUpload = null;
            $fiscalData = null;

            if (! $isForeign) {
                $csfUpload = $extractor->getTemporaryUpload((string) $data['csf_upload_token'], $request->session());

                if (! $csfUpload) {
                    return back()
                        ->withErrors(['csf_upload_token' => 'La constancia fiscal debe cargarse y validarse nuevamente.'])
                        ->withInput();
                }

                $fiscalData = $csfUpload['parsed'] ?? null;

                if (! is_array($fiscalData)) {
                    return back()
                        ->withErrors(['csf_upload_token' => 'No fue posible recuperar los datos fiscales analizados de la constancia.'])
                        ->withInput();
                }

                $fiscalValidator = Validator::make(
                    [
                        'rfc' => $fiscalData['rfc'] ?? null,
                    ],
                    [
                        'rfc' => ['required', 'unique:suppliers,rfc', new ValidRfc, new EfosNotListed],
                    ]
                );

                if ($fiscalValidator->fails()) {
                    return back()
                        ->withErrors(['csf_upload_token' => $fiscalValidator->errors()->first('rfc')])
                        ->withInput();
                }
            }

            $supplierData = $isForeign
                ? $this->buildForeignSupplierData($data, $repseData)
                : $this->buildNationalSupplierData($data, $fiscalData, $repseData);

            $supplier = Supplier::create($supplierData);
            $requirements->ensureForSupplier($supplier);

            if ($csfUpload) {
                $documentPath = $extractor->persistTemporaryUploadAsDocument($supplier->id, $csfUpload);

                $type = SupplierDocumentType::where('code', 'constancia_fiscal')->first();
                $requirement = $type ? $requirements->requirementForUpload($supplier, $type) : null;
                SupplierDocument::create([
                    'supplier_id' => $supplier->id,
                    'uploaded_by' => null,
                    'doc_type' => 'constancia_fiscal',
                    'supplier_document_type_id' => $type?->id,
                    'supplier_document_requirement_id' => $requirement?->id,
                    'path_file' => $documentPath,
                    'size_bytes' => $csfUpload['size_bytes'] ?? null,
                    'mime_type' => $csfUpload['mime_type'] ?? 'application/pdf',
                    'status' => 'pending_review',
                    'uploaded_at' => now(),
                ]);

                if ($requirement) {
                    $requirements->markSubmitted($requirement);
                }

                $supplier->recalculateDocumentStatus();
                $extractor->forgetTemporaryUpload((string) $data['csf_upload_token'], $request->session());
            }

            $this->notifyBuyersAboutNewSupplier($supplier);
            $this->sendWelcomeToSupplier($supplier, $data['password']);

            Auth::guard('supplier')->login($supplier);
            $request->session()->put('auth.guard', 'supplier');

            $message = $this->getSuccessMessage($repseData['provides_specialized_services']);

            return redirect()->route('supplier.documents.index')->with('status', $message);
        });
    }

    private function buildNationalSupplierData(array $data, array $fiscalData, array $repseData): array
    {
        $firstName = trim((string) ($fiscalData['first_name'] ?? ''));
        $lastName = trim((string) ($fiscalData['last_name'] ?? ''));

        if ($firstName === '' && ! empty($data['first_name'])) {
            $firstName = trim((string) $data['first_name']);
        }

        if ($lastName === '' && ! empty($data['last_name'])) {
            $lastName = trim((string) $data['last_name']);
        }

        return [
            'first_name' => $firstName !== '' ? $firstName : $fiscalData['company_name'],
            'last_name' => $lastName,
            'email' => $data['email'],
            'password' => $data['password'],
            'is_active' => true,
            'company_name' => $fiscalData['company_name'],
            'rfc' => strtoupper((string) $fiscalData['rfc']),
            'address' => $fiscalData['address'],
            'postal_code' => $fiscalData['postal_code'],
            'phone_number' => $data['phone_number'],
            'contact_person' => $data['contact_person'],
            'contact_phone' => $data['contact_phone'] ?? null,
            'supplier_type' => $data['supplier_type'],
            'person_type' => $fiscalData['person_type'],
            'tax_regimes' => SupplierFiscalCatalog::normalizeSelectedRegimes(
                $fiscalData['person_type'],
                $fiscalData['tax_regimes'] ?? []
            ),
            'economic_activity' => SupplierFiscalCatalog::normalizeActivities(
                $data['economic_activity'] ?? []
            ),
            'provides_specialized_services' => $repseData['provides_specialized_services'],
            'repse_registration_number' => $repseData['repse_registration_number'],
            'repse_expiry_date' => $repseData['repse_expiry_date'],
            'specialized_services_types' => $repseData['specialized_services_types'],
            'default_payment_terms' => $data['default_payment_terms'],
            'bank_name' => null,
            'account_number' => null,
            'clabe' => null,
            'currency' => null,
            'accepted_currencies' => $data['accepted_currencies'],
            'approval_status' => 'pending',
            'document_status' => 'pending',
        ];
    }

    private function buildForeignSupplierData(array $data, array $repseData): array
    {
        return [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'is_active' => true,
            'company_name' => $data['company_name'],
            'rfc' => strtoupper((string) $data['rfc']),
            'address' => $data['address'],
            'postal_code' => $data['postal_code'],
            'phone_number' => $data['phone_number'],
            'contact_person' => $data['contact_person'],
            'contact_phone' => $data['contact_phone'] ?? null,
            'supplier_type' => $data['supplier_type'],
            'person_type' => 'extranjero',
            'tax_regimes' => [],
            'economic_activity' => SupplierFiscalCatalog::normalizeActivities(
                $data['economic_activity'] ?? []
            ),
            'provides_specialized_services' => $repseData['provides_specialized_services'],
            'repse_registration_number' => $repseData['repse_registration_number'],
            'repse_expiry_date' => $repseData['repse_expiry_date'],
            'specialized_services_types' => $repseData['specialized_services_types'],
            'default_payment_terms' => $data['default_payment_terms'],
            'bank_name' => null,
            'account_number' => null,
            'clabe' => null,
            'currency' => null,
            'accepted_currencies' => $data['accepted_currencies'],
            'approval_status' => 'pending',
            'document_status' => 'pending',
        ];
    }

    private function sendWelcomeToSupplier(Supplier $supplier, string $plainPassword): void
    {
        try {
            $supplier->notify(new SupplierWelcomeNotification($plainPassword));
        } catch (\Exception $e) {
            Log::error('Error al enviar correo de bienvenida al proveedor.', [
                'supplier_id' => $supplier->id,
                'supplier_email' => $supplier->email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function notifyBuyersAboutNewSupplier(Supplier $supplier): void
    {
        try {
            $recipients = User::role(['buyer', 'superadmin'])->get();

            if ($recipients->isEmpty()) {
                Log::warning('No se encontraron usuarios con rol buyer o superadmin para notificar nueva alta de proveedor.', [
                    'supplier_id' => $supplier->id,
                    'supplier_rfc' => $supplier->rfc,
                ]);

                return;
            }

            foreach ($recipients as $recipient) {
                try {
                    $recipient->notify(new NewSupplierRegistrationForBuyerNotification($supplier));
                } catch (\Exception $e) {
                    Log::error('Error al enviar notificación de nueva alta de proveedor.', [
                        'supplier_id' => $supplier->id,
                        'supplier_rfc' => $supplier->rfc,
                        'recipient_id' => $recipient->id,
                        'recipient_email' => $recipient->email,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Error general al notificar nueva alta de proveedor.', [
                'supplier_id' => $supplier->id,
                'supplier_rfc' => $supplier->rfc,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function prepareRepseData(array $data): array
    {
        $providesSpecializedServices = ($data['provides_specialized_services'] ?? 0) == 1;

        if (! $providesSpecializedServices) {
            return [
                'provides_specialized_services' => false,
                'repse_registration_number' => null,
                'repse_expiry_date' => null,
                'specialized_services_types' => null,
            ];
        }

        $specializedServices = $data['specialized_services_types'] ?? [];

        if (in_array('otros', $specializedServices) && ! empty($data['otros_descripcion'])) {
            $key = array_search('otros', $specializedServices);
            if ($key !== false) {
                $specializedServices[$key] = 'otros: '.trim($data['otros_descripcion']);
            }
        }

        return [
            'provides_specialized_services' => true,
            'repse_registration_number' => $this->formatRepseNumber($data['repse_registration_number'] ?? ''),
            'repse_expiry_date' => $data['repse_expiry_date'] ?? null,
            'specialized_services_types' => ! empty($specializedServices) ? $specializedServices : null,
        ];
    }

    private function formatRepseNumber(string $number): ?string
    {
        if (empty($number)) {
            return null;
        }

        $number = strtoupper(trim($number));

        if (! str_starts_with($number, 'REPSE-')) {
            $number = 'REPSE-'.$number;
        }

        return $number;
    }

    private function getSuccessMessage(bool $providesSpecializedServices): string
    {
        $baseMessage = 'Cuenta creada exitosamente. Por favor, carga tus documentos en la sección Documentación.';

        if ($providesSpecializedServices) {
            $baseMessage .= ' Como proveedor de servicios especializados, asegúrate de subir también tu certificado REPSE.';
        }

        return $baseMessage;
    }
}
