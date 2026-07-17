<?php

namespace Tests\Unit;

use App\Services\InfonavitQrValidationService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InfonavitQrValidationServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['services.infonavit.browser_enabled' => false]);
    }

    public function test_it_submits_the_rfc_form_and_extracts_the_official_result(): void
    {
        $qrUrl = 'https://portalmx.infonavit.org.mx/wps/portal/infonavitmx/mx2/patrones/tramites_adicionales/constancia_situacion_fiscal/consulta_tramite_oficio/!ut/p/z1/state/dz/d5/L2dBISEvZ0FBIS9nQSEh/?datos=encrypted';

        Http::fake([
            $qrUrl => Http::response(<<<'HTML'
                <form method="post" action="?consulta=1">
                    <input type="hidden" name="token" value="abc">
                    <input type="text" name="rfcConsulta">
                </form>
                HTML),
            'https://portalmx.infonavit.org.mx/wps/portal/infonavitmx/mx2/patrones/tramites_adicionales/constancia_situacion_fiscal/consulta_tramite_oficio/!ut/p/z1/state/dz/d5/L2dBISEvZ0FBIS9nQSEh/?consulta=1' => Http::response(<<<'HTML'
                <dl>
                    <dt>RFC</dt><dd>SGT220531R7A</dd>
                    <dt>Fecha de oficio</dt><dd>13/01/2025</dd>
                    <dt>Estatus cumplimiento</dt><dd>Sin adeudo</dd>
                </dl>
                HTML),
        ]);

        $result = app(InfonavitQrValidationService::class)->validate($qrUrl, 'SGT220531R7A');

        $this->assertSame('2025-01-13', $result['issued_at']->toDateString());
        $this->assertSame('SGT220531R7A', $result['rfc']);
        $this->assertSame('POSITIVA', $result['compliance_status']);
        Http::assertSent(fn (Request $request) => $request->url() === 'https://portalmx.infonavit.org.mx/wps/portal/infonavitmx/mx2/patrones/tramites_adicionales/constancia_situacion_fiscal/consulta_tramite_oficio/!ut/p/z1/state/dz/d5/L2dBISEvZ0FBIS9nQSEh/?consulta=1'
            && $request['rfcConsulta'] === 'SGT220531R7A'
            && $request['token'] === 'abc');
    }
}
