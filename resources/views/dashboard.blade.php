@extends('layouts.zircos')

@section('title', 'Dashboard')

@section('page.title', 'Dashboard')

@section('content')
    @include('dashboard.partials.board', ['dashboard' => $dashboard, 'homeLabel' => 'Dashboard'])
@endsection
