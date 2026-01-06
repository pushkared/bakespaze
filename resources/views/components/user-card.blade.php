@props(['name'=>'User','role'=>'Member','avatar'=>null,'theme'=>'green','size'=>'md','seat'=>null])

@php
  $avatarSrc = $avatar;
  if ($avatarSrc && \Illuminate\Support\Str::startsWith($avatarSrc, ['http://', 'https://']) === false) {
    $avatarSrc = \Illuminate\Support\Facades\Storage::url($avatarSrc);
  }
@endphp

<div class="user-card user-{{ $theme }} user-{{ $size }}" data-seat="{{ $seat }}" tabindex="0" role="group" aria-label="{{ $name }} - {{ $role }}">
  <div class="avatar-wrap">
    <img src="{{ $avatarSrc ?: asset('images/user-icon.svg') }}" alt="{{ $name }}" onerror="this.src='{{ asset('images/user-icon.svg') }}'">
  </div>
  <div class="meta">
    <div class="name">{{ $name }}</div>
    <div class="role">{{ $role }}</div>
  </div>
</div>
