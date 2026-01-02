@extends('layouts.app')

@section('title', 'Chat')

@section('content')
<section class="chat-page">
  <div class="chat-shell"
       data-user-id="{{ auth()->id() }}"
       data-user-name="{{ auth()->user()->name }}">
    <aside class="chat-sidebar">
      <div class="chat-sidebar-head">
        <div class="chat-title">Chats</div>
        <button class="chat-new-btn" type="button" id="chat-new-btn">New</button>
      </div>
      <div class="chat-search">
        <input type="search" id="chat-search" placeholder="Search chats or people" autocomplete="off">
      </div>
      <div class="chat-list" id="chat-list"></div>
    </aside>

    <div class="chat-main">
      <div class="chat-empty" id="chat-empty">
        <div class="chat-empty-title">Select a conversation</div>
        <div class="chat-empty-sub">Start a new chat to begin messaging.</div>
      </div>
      <div class="chat-thread hidden" id="chat-thread">
        <div class="chat-thread-head">
          <div>
            <div class="chat-thread-title" id="chat-thread-title"></div>
            <div class="chat-thread-meta" id="chat-thread-meta"></div>
          </div>
          <div class="chat-thread-actions">
            <button class="chat-action-btn" type="button" id="chat-add-people">Add people</button>
          </div>
        </div>
        <div class="chat-messages" id="chat-messages"></div>
        <div class="chat-reply-preview hidden" id="chat-reply-preview">
          <div class="chat-reply-label">Replying to</div>
          <div class="chat-reply-content" id="chat-reply-content"></div>
          <button type="button" class="chat-reply-cancel" id="chat-reply-cancel">Cancel</button>
        </div>
        <form class="chat-composer" id="chat-form">
          <label class="chat-attach-btn">
            <input type="file" id="chat-attachments" multiple>
            <span>Attach</span>
          </label>
          <button type="button" class="chat-emoji-btn" id="chat-emoji-btn">Emoji</button>
          <textarea id="chat-input" placeholder="Type a message" rows="1"></textarea>
          <button type="submit" class="chat-send-btn">Send</button>
        </form>
        <div class="chat-emoji-picker hidden" id="chat-emoji-picker"></div>
      </div>
    </div>
  </div>
</section>

<div class="chat-modal hidden" id="chat-modal">
  <div class="chat-modal-card">
    <div class="chat-modal-head">
      <div class="chat-modal-title">Start a chat</div>
      <button class="chat-modal-close" type="button" id="chat-modal-close">Close</button>
    </div>
    <div class="chat-modal-body">
      <div class="chat-modal-row">
        <label class="chat-modal-label">Type</label>
        <div class="chat-modal-toggle">
          <label><input type="radio" name="chat-type" value="direct" checked> Direct</label>
          <label><input type="radio" name="chat-type" value="group"> Group</label>
        </div>
      </div>
      <div class="chat-modal-row">
        <label class="chat-modal-label" for="chat-group-name">Group name</label>
        <input type="text" id="chat-group-name" placeholder="Team Alpha" />
      </div>
      <div class="chat-modal-row">
        <label class="chat-modal-label">People</label>
        <div class="chat-people" id="chat-people">
          @foreach($users as $user)
            @if($user->id !== auth()->id())
              <label class="chat-person">
                <input type="checkbox" value="{{ $user->id }}">
                <span class="chat-person-name">{{ $user->name }}</span>
                <span class="chat-person-meta">{{ $user->email }}</span>
              </label>
            @endif
          @endforeach
        </div>
      </div>
    </div>
    <div class="chat-modal-actions">
      <button class="chat-create-btn" type="button" id="chat-create-btn">Create chat</button>
    </div>
  </div>
</div>

<script>
  window.__CHAT_CONFIG__ = {
    userId: {{ auth()->id() }},
    userName: @json(auth()->user()->name),
    reverb: {
      key: @json(env('REVERB_APP_KEY')),
      host: @json(env('REVERB_HOST', '127.0.0.1')),
      port: @json(env('REVERB_PORT', 8080)),
      scheme: @json(env('REVERB_SCHEME', 'http'))
    }
  };
</script>
<script src="https://cdn.jsdelivr.net/npm/pusher-js@8.4.0/dist/web/pusher.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.16.1/dist/echo.iife.js"></script>
<script src="{{ asset('js/chat.js') }}"></script>
@endsection
