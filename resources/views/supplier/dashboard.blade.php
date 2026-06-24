@extends('layouts.zircos-supplier')

@section('title', 'Portal de Proveedores - Dashboard')

@section('page.title', 'Portal de Proveedores')

@section('page.breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ url('/') }}">Inicio</a></li>
    <li class="breadcrumb-item active">Dashboard Proveedor</li>
@endsection

@section('content')
    @include('dashboard.partials.board', ['dashboard' => $dashboard, 'homeLabel' => 'Portal'])
@endsection
