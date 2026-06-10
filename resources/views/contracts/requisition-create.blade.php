{{-- resources/views/contracts/requisition-create.blade.php --}}
@extends('layouts.zircos')
@section('title', 'Nueva Requisición por Contrato')
@section('page.title', 'Nueva Requisición por Contrato')

@section('page.breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('requisitions.index') }}">Requisiciones</a></li>
    <li class="breadcrumb-item active">Por contrato</li>
@endsection

@section('content')
<div class="container-fluid">
    @livewire('contract-requisition-form')
</div>
@endsection
