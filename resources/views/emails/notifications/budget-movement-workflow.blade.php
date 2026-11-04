@extends('emails.layout')

@section('title', $subject)
@section('heading'){{ $heading }}@endsection

@section('content')
@php
    $stepColors = [
        'done' => ['#17815c', '#17815c'],
        'now' => ['#1a4b96', '#1a4b96'],
        'returned' => ['#c98a00', '#9a6b00'],
        'failed' => ['#c24141', '#9b1c1c'],
        'pending' => ['#d7dee8', '#7b8aa0'],
    ];
    $stepMarks = ['done' => '✓ ', 'returned' => '↩ ', 'failed' => '✕ ', 'now' => '', 'pending' => ''];
    $calloutType = ['warning' => 'warning', 'danger' => 'danger', 'success' => 'success'][$tone] ?? null;
@endphp

<p style="font-size:14px;color:#444444;line-height:1.7;margin:0 0 18px;">
    Hola{{ $name ? ' '.$name : '' }}, {{ \Illuminate\Support\Str::lcfirst($intro) }}
</p>

{{-- Ruta de aprobación --}}
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin:0 0 20px;table-layout:fixed;">
    <tr>
        @foreach($steps as $step)
            @php([$bar, $text] = $stepColors[$step['state']] ?? $stepColors['pending'])
            <td align="center" valign="top" style="border-top:4px solid {{ $bar }};padding:8px 4px 0;font-size:11px;color:{{ $text }};line-height:1.4;">
                <strong style="display:block;font-size:12px;color:{{ $text }};">{{ $stepMarks[$step['state']] ?? '' }}{{ $step['label'] }}</strong>
                {{ $step['who'] ?? '' }}
            </td>
        @endforeach
    </tr>
</table>

{{-- Partidas --}}
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin:0 0 18px;">
    <tr>
        @foreach($lines as $line)
            @if(! $loop->first)
                <td width="28" align="center" valign="middle" style="font-size:18px;font-weight:700;color:#1a4b96;">&rarr;</td>
            @endif
            <td valign="top" style="border:1px solid #e3e8f0;border-radius:8px;padding:10px 12px;font-size:12.5px;line-height:1.5;color:#1f2d44;">
                <span style="display:block;font-size:11px;color:#6b7a90;text-transform:uppercase;letter-spacing:1px;">{{ $line['label'] }}</span>
                <strong style="display:block;font-size:13px;color:#0d2b5e;">{{ $line['center'] }}</strong>
                {{ $line['account'] }}<br>{{ $line['month'] }}<br>
                <strong style="color:{{ $line['negative'] ? '#c24141' : '#17815c' }};">{{ $line['amount'] }}</strong>
            </td>
        @endforeach
    </tr>
</table>

@if($reason && $reason['text'])
    <x-emails.callout :type="$tone === 'danger' ? 'danger' : 'warning'" :title="$tone === 'danger' ? 'Motivo del rechazo' : 'Qué hay que corregir'">
        “{{ $reason['text'] }}” — {{ $reason['by'] }}, {{ $reason['at'] }}
    </x-emails.callout>
@elseif($calloutType === 'success')
    <x-emails.callout type="success">El monto ya se aplicó al presupuesto de las partidas indicadas.</x-emails.callout>
@endif

@if($isPending && $justification)
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin:0 0 18px;">
        <tr>
            <td style="border:1px solid #e3e8f0;border-radius:8px;padding:10px 14px;font-size:13px;line-height:1.6;color:#1f2d44;">
                <span style="display:block;font-size:11px;color:#6b7a90;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;">Justificación de {{ $creatorName ?? 'el solicitante' }}</span>
                {{ $justification }}
            </td>
        </tr>
    </table>
@endif

@if($attached->isNotEmpty() || $notAttached->isNotEmpty())
    <p style="font-size:13px;color:#1f2d44;line-height:1.6;margin:0 0 18px;">
        @if($attached->isNotEmpty())
            <strong>Evidencia adjunta:</strong> {{ $attached->pluck('original_name')->join(', ') }}.
        @endif
        @if($notAttached->isNotEmpty())
            <br><span style="color:#6b7a90;">Por su tamaño, {{ $notAttached->count() === 1 ? 'este documento está' : 'estos documentos están' }} solo en el portal: {{ $notAttached->pluck('original_name')->join(', ') }}.</span>
        @endif
    </p>
@endif

@include('emails.partials.button', ['url' => $url, 'label' => $buttonLabel])

<p style="font-size:12px;color:#6b7a90;line-height:1.6;margin:18px 0 0;">
    {{ $folio }} · {{ $amount }} MXN.@if($alsoCan) {{ $alsoCan }}@endif
</p>
@endsection
