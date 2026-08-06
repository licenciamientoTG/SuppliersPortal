@extends('layouts.zircos')

@section('title', 'Dashboard')

@section('page.title', '')

@section('content')
    <div class="container-fluid py-4 dashboard-board">
        @include('dashboard.partials.hero', ['dashboard' => $dashboard, 'homeLabel' => 'Dashboard'])

        <livewire:dashboard.dashboard-content lazy="on-scroll" />
    </div>
@endsection

@push('styles')
    <style>
        .dashboard-board {
            --dash-primary: #0f3d75;
        }

        .dashboard-hero {
            background: linear-gradient(135deg, #0b2d57 0%, #114b8b 55%, #1a5da5 100%);
            box-shadow: 0 18px 50px rgba(15, 61, 117, 0.1);
            color: #fff;
        }

        .dashboard-eyebrow,
        .dashboard-context-badge {
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.22);
            color: #fff;
        }

        .dashboard-eyebrow {
            border-radius: 999px;
            display: inline-block;
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            padding: 0.45rem 0.8rem;
            text-transform: uppercase;
        }

        .dashboard-title {
            font-size: clamp(2rem, 3vw, 3rem);
            font-weight: 800;
            letter-spacing: -0.03em;
        }

        .dashboard-subtitle {
            color: rgba(255, 255, 255, 0.84);
            max-width: 60ch;
        }

        .dashboard-user-badge {
            background: rgba(255, 255, 255, 0.96);
            color: #0f2744;
        }

        .dashboard-notifications,
        .dashboard-loading-card {
            border-radius: 1rem;
        }

        .dashboard-loading-card {
            border: 1px solid #e2e9f0;
        }

        .dashboard-loading-icon {
            align-items: center;
            background: #e8f0fb;
            border-radius: 0.75rem;
            color: #188ae2;
            display: inline-flex;
            font-size: 1.35rem;
            height: 2.75rem;
            justify-content: center;
            width: 2.75rem;
        }

        @media (prefers-reduced-motion: reduce) {
            .dashboard-board * {
                scroll-behavior: auto;
                transition: none !important;
            }
        }
    </style>
@endpush
