@extends('layouts.app')

@section('title', 'Profile')

@section('content')
<section class="profile-page">
  <div class="vo-pattern"></div>
  <div class="profile-shell">
    <header class="profile-head ios-settings-head">
      <div class="eyebrow">Settings</div>
      <h1>Profile</h1>
    </header>

    <div class="ios-settings-card profile-hero">
      <div class="profile-avatar">
        <label class="avatar-upload" for="profile-avatar-input" @if($viewer->id !== $user->id) aria-disabled="true" @endif>
          @if(!empty($avatarUrl))
            <img src="{{ $avatarUrl }}" alt="{{ $user->name }}">
          @else
            <div class="avatar-fallback">{{ strtoupper(substr($user->name ?? 'U', 0, 1)) }}</div>
          @endif
          @if($viewer->id === $user->id)
            <span class="avatar-edit">Edit</span>
          @endif
        </label>
      </div>
      <div class="profile-info">
        <div class="profile-name">{{ $user->name }}</div>
        <div class="profile-email">{{ $user->email }}</div>
        <div class="profile-meta-row">
          <div class="profile-meta">
            <span class="label">Punched In</span>
            <span class="value">{{ $punchInTime ?? 'Not punched' }}</span>
          </div>
          <div class="profile-meta">
            <span class="label">Total Hours</span>
            <span class="value">{{ $todayHours }}</span>
          </div>
        </div>
      </div>
      @if($viewer->id !== $user->id)
        <div class="profile-actions">
          <a class="ios-row-action" href="{{ route('chat.index') }}?user={{ $user->id }}">Chat</a>
          <a class="ios-row-action" href="{{ route('tasks.index') }}?open_modal=1&assign_to={{ $user->id }}">Assign Task</a>
        </div>
      @endif
    </div>

    @if($viewer->id === $user->id)
      <div class="ios-settings-card">
        @if(session('status'))
          <div class="profile-alert">{{ session('status') }}</div>
        @endif
        <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="ios-form">
          @csrf
          <input type="file" id="profile-avatar-input" name="avatar" accept="image/*" hidden>
          <div class="ios-row">
            <span>Name</span>
            <input type="text" name="name" value="{{ old('name', $user->name) }}" required>
          </div>
          <div class="ios-row">
            <span>Email</span>
            <input type="email" name="email" value="{{ old('email', $user->email) }}" readonly>
          </div>
          <div class="ios-row actions">
            <button type="submit" class="pill-btn solid">Update Profile</button>
          </div>
        </form>
      </div>
    @else
      <input type="file" id="profile-avatar-input" hidden>
    @endif

    <div class="ios-settings-card">
      <div class="ios-section-title">Assigned Tasks</div>
      <div class="profile-task-list">
        @forelse($assignedTasks as $task)
          <div class="profile-task ios-row">
            <div>
              <div class="title">{{ $task->title }}</div>
              <div class="meta">
                {{ $task->workspace?->name ?? 'Workspace' }}
                - {{ $task->due_date ? $task->due_date->format('d M') : 'No due' }}
                - {{ $task->status ?? 'open' }}
              </div>
            </div>
          </div>
        @empty
          @if(!$hasSharedWorkspace)
            <div class="muted">Tasks are visible only within shared workspaces.</div>
          @else
          <div class="muted">No assigned tasks.</div>
          @endif
        @endforelse
      </div>
    </div>

    <div class="ios-settings-card">
      <div class="ios-section-title">Today Tasks</div>
      <div class="profile-task-list">
        @forelse($todayTasks as $task)
          <div class="profile-task ios-row">
            <div>
              <div class="title">{{ $task->title }}</div>
              <div class="meta">
                {{ $task->workspace?->name ?? 'Workspace' }}
                - {{ $task->due_date ? $task->due_date->format('d M') : 'No due' }}
                - {{ $task->status ?? 'open' }}
              </div>
            </div>
          </div>
        @empty
          @if(!$hasSharedWorkspace)
            <div class="muted">Tasks are visible only within shared workspaces.</div>
          @else
          <div class="muted">No tasks due today.</div>
          @endif
        @endforelse
      </div>
    </div>
  </div>
</section>
<div class="cropper-modal" id="avatar-crop-modal" aria-hidden="true">
  <div class="cropper-card">
    <div class="cropper-header">
      <div class="cropper-title">Crop photo</div>
      <button type="button" class="cropper-close" id="avatar-crop-cancel" aria-label="Close">✕</button>
    </div>
    <div class="cropper-stage" id="avatar-crop-stage">
      <img id="avatar-crop-image" alt="Crop preview">
      <div class="cropper-mask"></div>
    </div>
    <div class="cropper-controls">
      <label>
        <span>Zoom</span>
        <input type="range" id="avatar-crop-zoom" min="1" max="3" step="0.01" value="1">
      </label>
    </div>
    <div class="cropper-actions">
      <button type="button" class="pill-btn ghost" id="avatar-crop-cancel-2">Cancel</button>
      <button type="button" class="pill-btn solid" id="avatar-crop-apply">Use photo</button>
    </div>
  </div>
</div>
@push('scripts')
<script>
  (function(){
    const avatarInput = document.getElementById('profile-avatar-input');
    const avatarTrigger = document.querySelector('.avatar-upload');
    const modal = document.getElementById('avatar-crop-modal');
    const stage = document.getElementById('avatar-crop-stage');
    const cropImage = document.getElementById('avatar-crop-image');
    const zoomInput = document.getElementById('avatar-crop-zoom');
    const applyBtn = document.getElementById('avatar-crop-apply');
    const cancelBtn = document.getElementById('avatar-crop-cancel');
    const cancelBtn2 = document.getElementById('avatar-crop-cancel-2');
    const cropSize = 240;
    let scale = 1;
    let baseScale = 1;
    let translateX = 0;
    let translateY = 0;
    let isDragging = false;
    let lastX = 0;
    let lastY = 0;
    let objectUrl = null;
    let originalFile = null;

    if (avatarInput && avatarTrigger) {
      avatarTrigger.addEventListener('click', (e) => {
        if (avatarTrigger.getAttribute('aria-disabled') === 'true') return;
        avatarInput.click();
      });
    }

    const clamp = (value, min, max) => Math.min(max, Math.max(min, value));
    const syncTransform = () => {
      const imgWidth = cropImage.naturalWidth * scale;
      const imgHeight = cropImage.naturalHeight * scale;
      const minX = cropSize - imgWidth;
      const minY = cropSize - imgHeight;
      translateX = clamp(translateX, minX, 0);
      translateY = clamp(translateY, minY, 0);
      cropImage.style.transform = `translate(${translateX}px, ${translateY}px) scale(${scale})`;
    };

    const openModal = (file) => {
      originalFile = file;
      if (objectUrl) URL.revokeObjectURL(objectUrl);
      objectUrl = URL.createObjectURL(file);
      cropImage.src = objectUrl;
      modal.classList.add('is-open');
      modal.setAttribute('aria-hidden', 'false');
    };

    const closeModal = () => {
      modal.classList.remove('is-open');
      modal.setAttribute('aria-hidden', 'true');
      if (objectUrl) {
        URL.revokeObjectURL(objectUrl);
        objectUrl = null;
      }
      cropImage.removeAttribute('src');
      originalFile = null;
    };

    if (avatarInput) {
      avatarInput.addEventListener('change', (event) => {
        const file = event.target.files && event.target.files[0];
        if (!file) return;
        openModal(file);
      });
    }

    cropImage.addEventListener('load', () => {
      baseScale = Math.max(cropSize / cropImage.naturalWidth, cropSize / cropImage.naturalHeight);
      scale = baseScale;
      translateX = (cropSize - cropImage.naturalWidth * scale) / 2;
      translateY = (cropSize - cropImage.naturalHeight * scale) / 2;
      zoomInput.min = baseScale.toFixed(2);
      zoomInput.max = (baseScale * 3).toFixed(2);
      zoomInput.value = scale.toFixed(2);
      syncTransform();
    });

    if (stage) {
      stage.addEventListener('pointerdown', (event) => {
        if (!cropImage.src) return;
        isDragging = true;
        lastX = event.clientX;
        lastY = event.clientY;
        stage.setPointerCapture(event.pointerId);
      });
      stage.addEventListener('pointermove', (event) => {
        if (!isDragging) return;
        translateX += event.clientX - lastX;
        translateY += event.clientY - lastY;
        lastX = event.clientX;
        lastY = event.clientY;
        syncTransform();
      });
      stage.addEventListener('pointerup', () => { isDragging = false; });
      stage.addEventListener('pointercancel', () => { isDragging = false; });
    }

    if (zoomInput) {
      zoomInput.addEventListener('input', () => {
        scale = parseFloat(zoomInput.value);
        syncTransform();
      });
    }

    const applyCrop = () => {
      if (!originalFile) return;
      const canvas = document.createElement('canvas');
      canvas.width = cropSize;
      canvas.height = cropSize;
      const ctx = canvas.getContext('2d');
      ctx.drawImage(
        cropImage,
        translateX,
        translateY,
        cropImage.naturalWidth * scale,
        cropImage.naturalHeight * scale
      );
      canvas.toBlob((blob) => {
        if (!blob) return;
        const file = new File([blob], originalFile.name, { type: blob.type });
        const dataTransfer = new DataTransfer();
        dataTransfer.items.add(file);
        avatarInput.files = dataTransfer.files;

        const preview = avatarTrigger ? avatarTrigger.querySelector('img') : null;
        if (preview) {
          preview.src = URL.createObjectURL(file);
        } else if (avatarTrigger) {
          const img = document.createElement('img');
          img.src = URL.createObjectURL(file);
          avatarTrigger.innerHTML = '';
          avatarTrigger.appendChild(img);
        }
        closeModal();
      }, 'image/jpeg', 0.92);
    };

    if (applyBtn) {
      applyBtn.addEventListener('click', applyCrop);
    }
    const cancelHandler = () => {
      if (avatarInput) avatarInput.value = '';
      closeModal();
    };
    if (cancelBtn) cancelBtn.addEventListener('click', cancelHandler);
    if (cancelBtn2) cancelBtn2.addEventListener('click', cancelHandler);
  })();
</script>
@endpush
@endsection
