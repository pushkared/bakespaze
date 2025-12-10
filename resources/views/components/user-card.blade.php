@props(['name'=>'User','role'=>'Member','avatar'=>'avatar.jpg','theme'=>'green','size'=>'md','seat'=>null])

<div class="user-card user-{{ $theme }} user-{{ $size }}" data-seat="{{ $seat }}" tabindex="0" role="group" aria-label="{{ $name }} - {{ $role }}">
  <div class="avatar-wrap">
    <img src="{{ asset('images/' . $avatar) }}" alt="{{ $name }}" onerror="this.src='{{ asset('images/user-icon.svg') }}'">
  </div>
  <div class="meta">
    <div class="name">{{ $name }}</div>
    <div class="role">{{ $role }}</div>
  </div>
</div>
