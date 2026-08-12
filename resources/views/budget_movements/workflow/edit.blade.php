@extends('layouts.zircos')
@section('title','Corregir movimiento presupuestal')
@section('content')
@include('budget_movements.workflow._styles')
<div class="bm-shell"><header class="bm-hero"><div><span class="bm-kicker">Solicitud devuelta</span><h1>Corrige y reenvía el movimiento #{{ $budgetMovement->id }}</h1><p>La transferencia volverá a validarse por el responsable del centro origen.</p></div></header><div class="bm-card">@include('budget_movements.workflow._form')</div></div>
@endsection
