<?php

namespace Tests\Feature;

use App\Models\Supplier;
use App\Models\SupplierDocument;
use App\Models\User;
use App\Notifications\NewSupplierRegistrationForBuyerNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SupplierRegistrationNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

        Role::create(['name' => 'buyer', 'guard_name' => 'web']);
    }

    public function test_successful_public_registration_notifies_all_buyers(): void
    {
        Notification::fake();
        Storage::fake('local');
        Storage::fake('public');
        Http::fake([
            'https://siat.sat.gob.mx/*' => Http::response($this->fakeSatHtmlFisica(), 200),
        ]);

        $buyers = User::factory()->count(2)->create();
        foreach ($buyers as $buyer) {
            $buyer->assignRole('buyer');
        }

        $parseResponse = $this->postJson(route('supplier.register.parse-csf'), [
            'csf' => UploadedFile::fake()->createWithContent('csf.pdf', $this->fakePdfWithSatUrl('https://siat.sat.gob.mx/app/qr/faces/pages/mobile/validadorqr.jsf?D1=10&D2=1&D3=14080261378_AORM681022FY5')),
        ]);

        $parseResponse->assertOk();

        $response = $this->post(route('register'), $this->validPayload([
            'email' => 'proveedor@example.com',
            'csf_upload_token' => $parseResponse->json('token'),
        ]));

        $response->assertRedirect(route('supplier.documents.index'));
        $this->assertAuthenticated('supplier');

        foreach ($buyers as $buyer) {
            Notification::assertSentTo($buyer, NewSupplierRegistrationForBuyerNotification::class);
        }

        $this->assertDatabaseHas('suppliers', [
            'company_name' => 'MOISES ALONSO RUBIO',
            'rfc' => 'AORM681022FY5',
            'email' => 'proveedor@example.com',
            'postal_code' => '33078',
            'approval_status' => 'pending',
            'document_status' => 'in_review',
        ]);

        $supplier = Supplier::where('rfc', 'AORM681022FY5')->firstOrFail();
        $this->assertSame('fisica', $supplier->person_type);
        $this->assertSame(['611', '612'], collect($supplier->tax_regimes)->pluck('code')->all());
        $this->assertSame(['Comercializacion de insumos'], $supplier->economic_activity);
        $this->assertDatabaseHas('supplier_documents', [
            'supplier_id' => $supplier->id,
            'doc_type' => 'constancia_fiscal',
            'status' => 'pending_review',
        ]);
        $this->assertTrue(Storage::disk('public')->exists(SupplierDocument::firstOrFail()->path_file));
    }

    public function test_registration_does_not_notify_when_rfc_is_in_efos(): void
    {
        Notification::fake();
        Storage::fake('local');
        Http::fake([
            'https://siat.sat.gob.mx/*' => Http::response(str_replace('AORM681022FY5', 'GHI123456T56', $this->fakeSatHtmlFisica()), 200),
        ]);

        $buyer = User::factory()->create(['email' => 'buyer@example.com']);
        $buyer->assignRole('buyer');

        DB::table('sat_efos_69b')->insert([
            'number' => 1,
            'rfc' => 'GHI123456T56',
            'company_name' => 'Proveedor EFOS',
            'situation' => 'Definitivo',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $parseResponse = $this->postJson(route('supplier.register.parse-csf'), [
            'csf' => UploadedFile::fake()->createWithContent('csf.pdf', $this->fakePdfWithSatUrl('https://siat.sat.gob.mx/app/qr/faces/pages/mobile/validadorqr.jsf?D1=10&D2=1&D3=14080261378_GHI123456T56')),
        ]);

        $response = $this->from(route('register'))
            ->post(route('register'), $this->validPayload([
                'email' => 'proveedor3@example.com',
                'csf_upload_token' => $parseResponse->json('token'),
            ]));

        $response->assertRedirect(route('register'));
        $response->assertSessionHasErrors('csf_upload_token');

        Notification::assertNothingSent();
        $this->assertDatabaseMissing('suppliers', ['rfc' => 'GHI123456T56']);
    }

    public function test_foreign_registration_does_not_require_csf_but_requires_confirmation(): void
    {
        Notification::fake();

        $response = $this->from(route('register'))
            ->post(route('register'), [
                'is_foreign' => '1',
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'email' => 'foreign@example.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
                'company_name' => 'Foreign Supplier LLC',
                'rfc' => 'XEXX010101000',
                'address' => 'One International Way',
                'postal_code' => '12345',
                'phone_number' => '6561234567',
                'contact_person' => 'Jane Doe',
                'contact_phone' => '6567654321',
                'supplier_type' => 'service',
                'provides_specialized_services' => '0',
                'economic_activity' => ['Consultoria'],
                'default_payment_terms' => 'NET_30',
            ]);

        $response->assertRedirect(route('register'));
        $response->assertSessionHasErrors('accepted_prefilled_data');
    }

    public function test_parse_csf_for_moral_person_returns_company_name_from_sat(): void
    {
        Storage::fake('local');
        Http::fake([
            'https://siat.sat.gob.mx/*' => Http::response($this->fakeSatHtmlMoral(), 200),
        ]);

        $response = $this->postJson(route('supplier.register.parse-csf'), [
            'csf' => UploadedFile::fake()->createWithContent(
                'csf.pdf',
                $this->fakePdfWithSatUrl('https://siat.sat.gob.mx/app/qr/faces/pages/mobile/validadorqr.jsf?D1=10&D2=1&D3=15010752710_ACO041014H30')
            ),
        ]);

        $response->assertOk()
            ->assertJsonPath('data.person_type', 'moral')
            ->assertJsonPath('data.company_name', 'ALVHER CORPORATIVO')
            ->assertJsonPath('data.first_name', '')
            ->assertJsonPath('data.last_name', '');
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Moises',
            'last_name' => 'Alonso Rubio',
            'email' => 'proveedor@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'company_name' => 'MOISES ALONSO RUBIO',
            'rfc' => 'AORM681022FY5',
            'supplier_type' => 'product_service',
            'address' => 'AVE. OSCAR FLORES NORTE 2011, INFONAVIT NORTE, DELICIAS, CHIHUAHUA',
            'postal_code' => '33078',
            'phone_number' => '6561234567',
            'contact_person' => 'MOISES ALONSO RUBIO',
            'contact_phone' => '6567654321',
            'provides_specialized_services' => '0',
            'economic_activity' => ['Comercializacion de insumos'],
            'default_payment_terms' => 'NET_30',
            'accepted_prefilled_data' => '1',
        ], $overrides);
    }

    private function fakePdfWithSatUrl(string $url): string
    {
        return "%PDF-1.4\n1 0 obj\n<< /Type /Annot /Subtype /Link /A << /S /URI /URI ({$url}) >> >>\nendobj\n%%EOF";
    }

    private function fakeSatHtmlFisica(): string
    {
        return <<<'HTML'
<ul><li>El RFC: AORM681022FY5, tiene asociada la siguiente información.</li></ul>
<ul>
  <li data-role="list-divider">Datos de Identificación</li>
  <li>
    <table><tbody>
      <tr><td>Nombre:</td><td>MOISES</td></tr>
      <tr><td>Apellido Paterno:</td><td>ALONSO</td></tr>
      <tr><td>Apellido Materno:</td><td>RUBIO</td></tr>
      <tr><td>Situación del contribuyente:</td><td>ACTIVO</td></tr>
    </tbody></table>
  </li>
</ul>
<ul>
  <li data-role="list-divider">Datos de Ubicación (domicilio fiscal, vigente)</li>
  <li>
    <table><tbody>
      <tr><td>Tipo de vialidad:</td><td>AVE.</td></tr>
      <tr><td>Nombre de la vialidad:</td><td>OSCAR FLORES NORTE</td></tr>
      <tr><td>Número exterior:</td><td>2011</td></tr>
      <tr><td>Colonia:</td><td>INFONAVIT NORTE</td></tr>
      <tr><td>Municipio o delegación:</td><td>DELICIAS</td></tr>
      <tr><td>Entidad Federativa:</td><td>CHIHUAHUA</td></tr>
      <tr><td>CP:</td><td>33078</td></tr>
      <tr><td>Correo electrónico:</td><td>proveedor@sat.test</td></tr>
    </tbody></table>
  </li>
</ul>
<ul>
  <li data-role="list-divider">Características fiscales (vigente)</li>
  <li>
    <table><tbody>
      <tr><td>Régimen:</td><td>Ingresos por Dividendos (socios y accionistas)</td></tr>
      <tr><td>Régimen:</td><td>Personas Físicas con Actividades Empresariales y Profesionales</td></tr>
    </tbody></table>
  </li>
</ul>
HTML;
    }
    private function fakeSatHtmlMoral(): string
    {
        return <<<'HTML'
<ul><li>El RFC: ACO041014H30, tiene asociada la siguiente información.</li></ul>
<ul>
  <li data-role="list-divider">Datos de Identificación</li>
  <li>
    <table><tbody>
      <tr><td>Denominación o Razón Social:</td><td>ALVHER CORPORATIVO</td></tr>
      <tr><td>Régimen de capital:</td><td>SA DE CV</td></tr>
      <tr><td>Situación del contribuyente:</td><td>ACTIVO</td></tr>
    </tbody></table>
  </li>
</ul>
<ul>
  <li data-role="list-divider">Datos de Ubicación (domicilio fiscal, vigente)</li>
  <li>
    <table><tbody>
      <tr><td>Tipo de vialidad:</td><td>CALLE</td></tr>
      <tr><td>Nombre de la vialidad:</td><td>EJIDO LABOR DE DOLORES</td></tr>
      <tr><td>Número exterior:</td><td>13202</td></tr>
      <tr><td>Colonia:</td><td>LABOR DE TERRAZAS</td></tr>
      <tr><td>Municipio o delegación:</td><td>CHIHUAHUA</td></tr>
      <tr><td>Entidad Federativa:</td><td>CHIHUAHUA</td></tr>
      <tr><td>CP:</td><td>31220</td></tr>
      <tr><td>Correo electrónico:</td><td>cpalmaesparza@hotmail.com</td></tr>
    </tbody></table>
  </li>
</ul>
<ul>
  <li data-role="list-divider">Características fiscales (vigente)</li>
  <li>
    <table><tbody>
      <tr><td>Régimen:</td><td>Régimen General de Ley Personas Morales</td></tr>
    </tbody></table>
  </li>
</ul>
HTML;
    }
}
