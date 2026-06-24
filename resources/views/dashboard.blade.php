@extends('layouts.zircos')

@section('title', 'Dashboard')

@section('page.title', '')

@section('content')
    @include('dashboard.partials.board', ['dashboard' => $dashboard, 'homeLabel' => 'Dashboard'])
@endsection
