@extends('layouts.zircos')

@section('title', 'Tablero de Cotización')

@section('page.title', 'Tablero de Cotización')

@section('page.breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ url('/') }}">Inicio</a></li>
    <li class="breadcrumb-item"><a href="{{ route('rfq.index') }}">Gestión de Cotizaciones</a></li>
    <li class="breadcrumb-item active">Tablero de Cotización</li>
@endsection

@section('content')
    @livewire('rfq.quotation-board', ['requisition' => $requisition])
@endsection
