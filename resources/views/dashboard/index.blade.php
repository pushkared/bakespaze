@extends('layouts.app')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
    <style>body.page { padding: 0; }</style>
@endpush

@section('content')
<div class="app-shell">
    <div class="main">
        <div class="content-area">
            <div class="room-root">
                <div class="room-bg" aria-hidden="true"></div>

                <div class="room-stage">
                    <div class="seats top-row" aria-hidden="true">
                        @include('components.user-card', ['name'=>'Vikas Mahtta','role'=>'Brand Head','avatar'=>'vikas.jpg','theme'=>'green','seat'=>'top1'])
                        @include('components.user-card', ['name'=>'Pushkar Kalra','role'=>'Tech Head','avatar'=>'pushkar.jpg','theme'=>'green','seat'=>'top2'])
                        @include('components.user-card', ['name'=>'Anmol Prasher','role'=>'Production Head','avatar'=>'anmol.jpg','theme'=>'green','seat'=>'top3'])
                        @include('components.user-card', ['name'=>'Manjot Singh','role'=>'Performance Head','avatar'=>'manjot.jpg','theme'=>'blue','seat'=>'top4'])
                    </div>

                    <div class="seat-single seat-left" aria-hidden="true">
                        @include('components.user-card', ['name'=>'Boss','role'=>'CEO','avatar'=>'boss.jpg','theme'=>'green','size'=>'lg','seat'=>'left'])
                    </div>

                    <div class="meeting-table" role="region" aria-label="Meeting table">
                        <div class="meeting-text">
                            Meeting<br>with Karan<br>in next<br><strong>10 mins</strong>
                        </div>
                    </div>

                    <div class="seat-single seat-right" aria-hidden="true">
                        @include('components.user-card', ['name'=>'Gaurav Arora','role'=>'Content Head','avatar'=>'gaurav.jpg','theme'=>'red','size'=>'lg','seat'=>'right'])
                    </div>

                    <div class="seats bottom-row" aria-hidden="true">
                        <div class="empty-chair"></div>
                        <div class="empty-chair"></div>
                        <div class="empty-chair"></div>
                        <div class="empty-chair"></div>
                    </div>

                    <button class="floating-task" aria-label="Open Tasks">
                        <img src="{{ asset('images/tasklist-icon.svg') }}" alt="Tasks">
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
<script src="{{ asset('js/dashboard.js') }}"></script>