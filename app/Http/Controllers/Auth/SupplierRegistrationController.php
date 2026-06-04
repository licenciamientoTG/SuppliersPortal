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

        return DB::transaction(function () use ($data) {
            // 1) Crear usuario
            $user = User::create([
                'name'       => trim($data['first_name'] . ' ' . $data['last_name']),
                'first_name' => $data['first_name'],
                'last_name'  => $data['last_name'],
                'email'      => $data['email'],
                'password'   => $data['password'], // Se hashea por cast "hashed".
                'is_active'  => true,
            ]);

            if (method_exists($user, 'assignRole')) {
                $user->assignRole('supplier');
            }

            // 2) Preparar datos REPSE
            $repseData = $this->prepareRepseData($data);

            // 3) Crear supplier (incluyendo campos REPSE)
            $supplier = Supplier::create([
                'user_id'       => $user->id,
                'company_name'  => $data['company_name'],
                'rfc'           => strtoupper($data['rfc']),
                'address'       => $data['address'],
                'postal_code'   => $data['postal_code'],
                'phone_number'  => $data['phone_number'],
                'email'         => $user->email,
                'contact_person' => $data['contact_person'],
                'contact_phone' => $data['contact_phone'] ?? null,
                'supplier_type' => $data['supplier_type'],
                'person_type'   => $data['person_type'],
                'tax_regimes'   => SupplierFiscalCatalog::normalizeSelectedRegimes(
                    $data['person_type'],
                    $data['tax_regimes'] ?? []
                ),
                'economic_activity' => SupplierFiscalCatalog::normalizeActivities(
                    $data['economic_activity'] ?? []
                ),

                // Nuevos campos REPSE
                'provides_specialized_services' => $repseData['provides_specialized_services'],
                'repse_registration_number'     => $repseData['repse_registration_number'],
                'repse_expiry_date'            => $repseData['repse_expiry_date'],
                'specialized_services_types'   => $repseData['specialized_services_types'],

                // Condiciones de pago por defecto
                'default_payment_terms' => $data['default_payment_terms'],

                // Bancarios: null hasta activación
                'bank_name'      => null,
                'account_number' => null,
                'clabe'          => null,
                'currency'       => null,
            ]);

            $this->notifyBuyersAboutNewSupplier($supplier);

            // 4) Correo de bienvenida al proveedor (con sus credenciales de acceso)
            $this->sendWelcomeToSupplier($user, $data['password']);

            // 5) Login + verificación de correo
            Auth::login($user);

            // 5) Mensaje personalizado según si requiere REPSE
            $message = $this->getSuccessMessage($repseData['provides_specialized_services']);

            return redirect()->route('dashboard')->with('status', $message);
        });
    }

    private function sendWelcomeToSupplier(User $user, string $plainPassword): void
    {
        try {
            $user->notify(new SupplierWelcomeNotification($plainPassword));
        } catch (\Exception $e) {
            Log::error('Error al enviar correo de bienvenida al proveedor.', [
                'user_id'    => $user->id,
                'user_email' => $user->email,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    private function notifyBuyersAboutNewSupplier(Supplier $supplier): void
    {
        try {
            // Destinatarios: Compras (buyer) y Superadministrador. El scope role()
            // con arreglo hace OR y devuelve cada usuario una sola vez (sin duplicados).
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

    /**
     * Preparar y limpiar datos REPSE
     */
    private function prepareRepseData(array $data): array
    {
        $providesSpecializedServices = ($data['provides_specialized_services'] ?? 0) == 1;

        if (!$providesSpecializedServices) {
            return [
                'provides_specialized_services' => false,
                'repse_registration_number'     => null,
                'repse_expiry_date'            => null,
                'specialized_services_types'   => null,
            ];
        }

        $specializedServices = $data['specialized_services_types'] ?? [];

        if (in_array('otros', $specializedServices) && !empty($data['otros_descripcion'])) {
            $key = array_search('otros', $specializedServices);
            if ($key !== false) {
                $specializedServices[$key] = 'otros: ' . trim($data['otros_descripcion']);
            }
        }

        return [
            'provides_specialized_services' => true,
            'repse_registration_number'     => $this->formatRepseNumber($data['repse_registration_number'] ?? ''),
            'repse_expiry_date'            => $data['repse_expiry_date'] ?? null,
            'specialized_services_types'   => !empty($specializedServices) ? $specializedServices : null,
        ];
    }

    /**
     * Formatear número REPSE (agregar prefijo si no existe)
     */
    private function formatRepseNumber(string $number): ?string
    {
        if (empty($number)) {
            return null;
        }

        $number = strtoupper(trim($number));

        if (!str_starts_with($number, 'REPSE-')) {
            $number = 'REPSE-' . $number;
        }

        return $number;
    }

    /**
     * Generar mensaje de éxito personalizado
     */
    private function getSuccessMessage(bool $providesSpecializedServices): string
    {
        $baseMessage = 'Cuenta creada exitosamente. Por favor, carga tus documentos en la sección `Documentación` en el menú lateral.';

        if ($providesSpecializedServices) {
            $baseMessage .= ' Como proveedor de servicios especializados, asegúrate de subir también tu certificado REPSE.';
        }

        return $baseMessage;
    }
}
