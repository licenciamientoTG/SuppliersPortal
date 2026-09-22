{{-- Membrete y pie del formato de OC/OCD. Requiere $logoPath, $company, $folio y $title. --}}
@php
    $companyName = $company?->legal_name ?? $company?->name ?? 'TotalGas';
    $companyContact = collect([
        $company?->phone ? 'Tel. '.$company->phone : null,
        $company?->email,
    ])->filter()->implode(' · ');
@endphp
<div class="footer"><table><tr>
    <td style="width:40px"><table class="dots" style="width:auto"><tr><td style="background:#034EA2"></td><td class="gap"></td><td style="background:#0095DA"></td><td class="gap"></td><td style="background:#009559"></td><td class="gap"></td><td style="background:#A6CE39"></td></tr></table></td>
    <td style="padding-left:8px">{{ collect([$companyName, $company?->rfc ? 'RFC '.$company->rfc : null, $company?->website, $folio])->filter()->implode(' · ') }} · Generado {{ now()->format('d/m/Y H:i') }}</td>
</tr></table></div>

<table class="head"><tr>
    <td style="width:50%">@if(is_file($logoPath))<img class="logo" src="{{ $logoPath }}" alt="TotalGas">@else<span class="logo-fallback">TOTALGAS</span>@endif</td>
    <td class="lh" style="width:50%"><b>{{ $companyName }}</b>@if($company?->rfc)<br>RFC {{ $company->rfc }}@endif @if($companyContact)<br>{{ $companyContact }}@endif</td>
</tr></table>
<div class="hair"></div>

<table><tr>
    <td style="vertical-align:bottom"><div class="title">{{ $title }}</div></td>
    <td class="r" style="vertical-align:bottom"><div class="k">Folio</div><div class="title-folio">{{ $folio }}</div></td>
</tr></table>
