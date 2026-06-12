<?php

namespace Tests\Unit;

use App\Services\CfdiXmlParser;
use PHPUnit\Framework\TestCase;

class CfdiXmlParserTest extends TestCase
{
    public function test_parser_extracts_basic_cfdi_fields(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<cfdi:Comprobante xmlns:cfdi="http://www.sat.gob.mx/cfd/4" xmlns:tfd="http://www.sat.gob.mx/TimbreFiscalDigital" Fecha="2026-05-18T10:30:00" Moneda="MXN" SubTotal="100.00" Total="116.00">
  <cfdi:Emisor Rfc="SUP010101AB1" Nombre="Proveedor"/>
  <cfdi:Receptor Rfc="TGA010101AA1" Nombre="TotalGas"/>
  <cfdi:Impuestos TotalImpuestosTrasladados="16.00">
    <cfdi:Traslados>
      <cfdi:Traslado Base="100.00" Impuesto="002" TipoFactor="Tasa" TasaOCuota="0.160000" Importe="16.00"/>
    </cfdi:Traslados>
  </cfdi:Impuestos>
  <cfdi:Complemento>
    <tfd:TimbreFiscalDigital UUID="11111111-2222-3333-4444-555555555555"/>
  </cfdi:Complemento>
</cfdi:Comprobante>
XML;

        $data = (new CfdiXmlParser())->parse($xml);

        $this->assertSame('11111111-2222-3333-4444-555555555555', $data['uuid']);
        $this->assertSame('SUP010101AB1', $data['issuer_rfc']);
        $this->assertSame('TGA010101AA1', $data['receiver_rfc']);
        $this->assertSame(100.00, $data['subtotal']);
        $this->assertSame(16.00, $data['iva_amount']);
        $this->assertSame(116.00, $data['total']);
        $this->assertSame('MXN', $data['currency']);
        $this->assertSame('2026-05-18 10:30:00', $data['issued_at']->format('Y-m-d H:i:s'));
    }

    public function test_parser_reports_invalid_xml_with_specific_message(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('El XML de factura no es válido:');

        (new CfdiXmlParser())->parse('<cfdi:Comprobante');
    }

    public function test_parser_requires_receiver_rfc(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<cfdi:Comprobante xmlns:cfdi="http://www.sat.gob.mx/cfd/4" xmlns:tfd="http://www.sat.gob.mx/TimbreFiscalDigital" Fecha="2026-05-18T10:30:00" Moneda="MXN" SubTotal="100.00" Total="116.00">
  <cfdi:Emisor Rfc="SUP010101AB1" Nombre="Proveedor"/>
  <cfdi:Receptor Nombre="TotalGas"/>
  <cfdi:Complemento>
    <tfd:TimbreFiscalDigital UUID="11111111-2222-3333-4444-555555555555"/>
  </cfdi:Complemento>
</cfdi:Comprobante>
XML;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('El XML no contiene el RFC del receptor.');

        (new CfdiXmlParser())->parse($xml);
    }
}
