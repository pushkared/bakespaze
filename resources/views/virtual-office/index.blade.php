@extends('layouts.app')

@section('title', 'Virtual Office')

@section('content')
<section class="vo-stage">
  <div class="vo-pattern"></div>
  <div class="vo-board room-stage">
    @php
      $seatClasses = ['seat-boss','seat-vikas','seat-pushkar','seat-anmol','seat-manjot','seat-gaurav','seat-right','seat-empty1','seat-empty2','seat-empty3'];
      $themes = ['seat-green','seat-aqua','seat-red'];
    @endphp

    @foreach(($members ?? collect())->take(10) as $index => $member)
      @php
        $seatClass = $seatClasses[$index] ?? 'seat-dynamic';
        $theme = $themes[$index % count($themes)];
      @endphp
      <div class="seat-card {{ $theme }} {{ $seatClass }}">
        <div class="avatar">
          <img src="{{ asset('images/user-icon.svg') }}" alt="{{ $member->user->name }}">
        </div>
        <div class="name">{{ $member->user->name }}</div>
        <div class="role">{{ ucfirst($member->role ?? 'Member') }}</div>
      </div>
    @endforeach

    <div class="board-table meeting-table">
      <div class="table-message">Meeting with {{ $workspace->name }} team</div>
    </div>
  </div>
</section>
@endsection
