@extends('layouts.zircos')
@section('title','Solicitar movimiento presupuestal')
@section('content')
@include('budget_movements.workflow._styles')
<div class="bm-shell"><header class="bm-hero"><div class="d-flex gap-3 align-items-center"><img class="bm-logo" src="{{ asset('images/logos/Logo.png') }}" alt="TotalGas"><div><span class="bm-kicker">Movimiento presupuestal</span><h1>Nueva solicitud</h1><p>Captura el movimiento y envíalo al responsable que corresponda.</p></div></div><a href="{{ route('budget_movements.index') }}" class="btn btn-light">Ver bandeja</a></header><div class="bm-card">@include('budget_movements.workflow._form')</div></div>
@endsection
