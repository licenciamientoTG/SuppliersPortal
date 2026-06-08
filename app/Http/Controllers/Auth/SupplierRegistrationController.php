<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterSupplierRequest;
use App\Models\Supplier;
use App\Models\User;
use App\Notifications\NewSupplierRegistrationForBuyerNotification;
use App\Notifications\SupplierWelcomeNotification;
use App\Support\SupplierFiscalCatalog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SupplierRegistrationController extends Controller
{
    public function create()
    {
        return view('auth.supplier-register');
    }

    public function store(RegisterSupplierRequest $request)
    {
        $data = $request->validated();

        return DB::transaction(function () use ($data, $request) {
            $repseData = $this->prepareRepseData($data);

            $supplier = Supplier::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'is_active' => true,
                'company_name' => $data['company_name'],
                'rfc' => strtoupper($data['rfc']),
                'address' => $data['address'],
                'postal_code' => $data['postal_code'],
                'phone_number' => $data['phone_number'],
                'contact_person' => $data['contact_person'],
                'contact_phone' => $data['contact_phone'] ?? null,
                'supplier_type' => $data['supplier_type'],
                'person_type' => $data['person_type'],
                'tax_regimes' => SupplierFiscalCatalog::normalizeSelectedRegimes(
                    $data['person_type'],
                    $data['tax_regimes'] ?? []
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
                'approval_status' => 'pending',
                'document_status' => 'pending',
            ]);

            $this->notifyBuyersAboutNewSupplier($supplier);
            $this->sendWelcomeToSupplier($supplier, $data['password']);

            Auth::guard('supplier')->login($supplier);
            $request->session()->put('auth.guard', 'supplier');

            $message = $this->getSuccessMessage($repseData['provides_specialized_services']);

            return redirect()->route('supplier.documents.index')->with('status', $message);
        });
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
                $specializedServices[$key] = 'otros: ' . trim($data['otros_descripcion']);
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
            $number = 'REPSE-' . $number;
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
