(() => {
  const config = window.__CHAT_CONFIG__;
  if (!config) return;

  const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  const listEl = document.getElementById('chat-list');
  const threadEl = document.getElementById('chat-thread');
  const emptyEl = document.getElementById('chat-empty');
  const titleEl = document.getElementById('chat-thread-title');
  const metaEl = document.getElementById('chat-thread-meta');
  const messagesEl = document.getElementById('chat-messages');
  const formEl = document.getElementById('chat-form');
  const inputEl = document.getElementById('chat-input');
  const attachEl = document.getElementById('chat-attachments');
  const replyPreviewEl = document.getElementById('chat-reply-preview');
  const replyContentEl = document.getElementById('chat-reply-content');
  const replyCancelEl = document.getElementById('chat-reply-cancel');
  const emojiBtn = document.getElementById('chat-emoji-btn');
  const emojiPicker = document.getElementById('chat-emoji-picker');
  const attachPreviewEl = document.getElementById('chat-attach-preview');
  const modal = document.getElementById('chat-modal');
  const modalClose = document.getElementById('chat-modal-close');
  const modalOpenButtons = document.querySelectorAll('.chat-new-btn');
  const modalCreate = document.getElementById('chat-create-btn');
  const groupNameInput = document.getElementById('chat-group-name');
  const addPeopleBtn = document.getElementById('chat-add-people');
  const backBtn = document.getElementById('chat-back');

  const state = {
    conversations: [],
    activeId: null,
    replyTo: null,
    echo: null,
    channelName: null,
    emojiMode: 'input',
    reactionMessageId: null,
    previewUrls: [],
  };

  const emojis = ['😀','😁','😂','🤣','😊','😍','😎','🤝','👏','🔥','🎉','👍','🙏','✅','❤️','😅','😮','😢','😡'];

  const getCookie = (name) => {
    const parts = document.cookie.split(';').map((part) => part.trim());
    for (const part of parts) {
      if (!part.startsWith(name + '=')) continue;
      return decodeURIComponent(part.substring(name.length + 1));
    }
    return '';
  };

  const fetchJson = (url, options = {}) => {
    const xsrf = getCookie('XSRF-TOKEN');
    const headers = {
      'X-CSRF-TOKEN': token || xsrf,
      'X-XSRF-TOKEN': xsrf,
      'X-Requested-With': 'XMLHttpRequest',
      'Accept': 'application/json',
      ...(options.headers || {}),
    };

    if (options.body instanceof FormData) {
      if (!options.body.has('_token')) {
        options.body.append('_token', token);
      }
    } else if (options.body && headers['Content-Type'] === 'application/json') {
      try {
        const payload = typeof options.body === 'string' ? JSON.parse(options.body) : options.body;
        if (!payload._token) payload._token = token || xsrf;
        options.body = JSON.stringify(payload);
      } catch (err) {
        // Ignore JSON parse failures; server will report CSRF issues.
      }
    }

    return fetch(url, {
      credentials: 'include',
      headers,
      ...options,
    }).then(async (res) => {
      if (!res.ok) {
        const text = await res.text();
        throw new Error(text);
      }
      return res.json();
    });
  };

  const renderEmojiPicker = () => {
    if (!emojiPicker) return;
    emojiPicker.innerHTML = emojis.map((emoji) => `<button class="chat-emoji" data-emoji="${emoji}" type="button">${emoji}</button>`).join('');
  };

  const toggleEmojiPicker = (show) => {
    if (!emojiPicker) return;
    emojiPicker.classList.toggle('hidden', !show);
  };

  const setReply = (message) => {
    state.replyTo = message;
    replyPreviewEl.classList.remove('hidden');
    replyContentEl.textContent = `${message.sender}: ${message.body || 'Attachment'}`;
  };

  const clearReply = () => {
    state.replyTo = null;
    replyPreviewEl.classList.add('hidden');
    replyContentEl.textContent = '';
  };

  const renderConversations = () => {
    if (!listEl) return;
    listEl.innerHTML = '';
    state.conversations.forEach((conv) => {
      const item = document.createElement('div');
      item.className = `chat-item ${state.activeId === conv.id ? 'active' : ''}`;
      item.dataset.id = conv.id;
      item.innerHTML = `
        <div class="chat-item-title">${conv.title}</div>
        <div class="chat-item-meta">${conv.last_message?.body || 'No messages yet'}</div>
      `;
      item.addEventListener('click', () => setActiveConversation(conv.id));
      listEl.appendChild(item);
    });
  };

  const formatTime = (iso) => {
    const date = new Date(iso);
    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  };

  const renderMessage = (message) => {
    const wrapper = document.createElement('div');
    wrapper.className = `chat-msg ${message.user_id === config.userId ? 'self' : ''}`;
    wrapper.dataset.id = message.id;

    const reactions = message.reactions || [];
    const grouped = reactions.reduce((acc, reaction) => {
      acc[reaction.emoji] = (acc[reaction.emoji] || 0) + 1;
      return acc;
    }, {});
    const reactionHtml = Object.keys(grouped).length
      ? `<div class="chat-reactions">${Object.entries(grouped).map(([emoji, count]) => `<span class="chat-reaction">${emoji} ${count}</span>`).join('')}</div>`
      : '';

    const attachments = message.attachments || [];
    const attachmentsHtml = attachments.length
      ? `<div class="chat-attachments">${attachments.map(att => {
          const name = att.name || 'Attachment';
          const url = att.url || '#';
          const previewUrl = att.preview_url || url;
          const ext = (name.split('.').pop() || '').toLowerCase();
          const isImage = ['jpg','jpeg','png','gif','webp','bmp'].includes(ext);
          if (isImage) {
            return `
              <div class="chat-attachment image">
                <a class="chat-attachment-thumb" href="${previewUrl}" target="_blank" rel="noopener">
                  <img src="${previewUrl}" alt="${name}">
                </a>
                <div class="chat-attachment-meta">
                  <span>${name}</span>
                  <a href="${url}" download>Download</a>
                </div>
              </div>
            `;
          }
          return `
            <div class="chat-attachment file">
              <span>${name}</span>
              <a href="${url}" target="_blank" rel="noopener" download>Download</a>
            </div>
          `;
        }).join('')}</div>`
      : '';

    wrapper.innerHTML = `
      <div class="chat-msg-time">${formatTime(message.created_at)}</div>
      <div class="chat-msg-body">
        ${message.reply_to ? `<div class="chat-reply">${message.reply_to.sender || ''}: ${message.reply_to.body || 'Attachment'}</div>` : ''}
        ${message.body ? `<div class="chat-bubble">${message.body}</div>` : ''}
        ${attachmentsHtml}
        ${reactionHtml}
        <div class="chat-msg-actions">
          <button class="chat-msg-action reply" data-action="reply" aria-label="Reply"></button>
          <button class="chat-msg-action react" data-action="react" aria-label="React"></button>
        </div>
      </div>
    `;

    wrapper.querySelectorAll('.chat-msg-action').forEach((btn) => {
      btn.addEventListener('click', () => {
        const action = btn.dataset.action;
        if (action === 'reply') {
          setReply(message);
          inputEl.focus();
        }
        if (action === 'react') {
          state.emojiMode = 'reaction';
          state.reactionMessageId = message.id;
          toggleEmojiPicker(true);
        }
      });
    });

    return wrapper;
  };

  const renderMessages = (messages) => {
    messagesEl.innerHTML = '';
    messages.forEach((message) => {
      messagesEl.appendChild(renderMessage(message));
    });
    messagesEl.scrollTop = messagesEl.scrollHeight;
  };

  const renderAttachmentPreview = (files) => {
    if (!attachPreviewEl) return;
    state.previewUrls.forEach((url) => URL.revokeObjectURL(url));
    state.previewUrls = [];
    if (!files || files.length === 0) {
      attachPreviewEl.innerHTML = '';
      attachPreviewEl.classList.add('hidden');
      return;
    }
    const items = Array.from(files).map((file) => {
      const isImage = file.type.startsWith('image/');
      let thumb = `<span class="file-ext">${(file.name.split('.').pop() || '').toUpperCase()}</span>`;
      if (isImage) {
        const url = URL.createObjectURL(file);
        state.previewUrls.push(url);
        thumb = `<img src="${url}" alt="${file.name}">`;
      }
      return `
        <div class="chat-attach-item">
          <div class="chat-attach-thumb">${thumb}</div>
          <div class="chat-attach-name">${file.name}</div>
        </div>
      `;
    }).join('');
    attachPreviewEl.innerHTML = items;
    attachPreviewEl.classList.remove('hidden');
  };

  const connectEcho = () => {
    if (state.echo) return;
    const isHttps = window.location.protocol === 'https:';
    const isLocal = ['localhost', '127.0.0.1'].includes(window.location.hostname);
    const host = (!['localhost', '127.0.0.1'].includes(config.reverb.host) && config.reverb.host)
      ? config.reverb.host
      : window.location.hostname;
    const port = isLocal ? config.reverb.port : (isHttps ? 443 : 80);

    state.echo = new window.Echo({
      broadcaster: 'pusher',
      key: config.reverb.key,
      wsHost: host,
      wsPort: port,
      wssPort: port,
      forceTLS: isHttps,
      enabledTransports: ['ws', 'wss'],
      disableStats: true,
      authEndpoint: '/broadcasting/auth',
      auth: {
        headers: {
          'X-CSRF-TOKEN': token,
        },
      },
    });
  };

  const subscribeConversation = (id) => {
    connectEcho();
    if (state.channelName) {
      state.echo.leave(state.channelName);
    }
    state.channelName = `conversation.${id}`;
    state.echo.private(state.channelName)
      .listen('.message.sent', (event) => {
        const message = event.payload || event;
        if (message.conversation_id !== state.activeId) return;
        messagesEl.appendChild(renderMessage(message));
        messagesEl.scrollTop = messagesEl.scrollHeight;
      });
  };

  const setActiveConversation = async (id) => {
    state.activeId = id;
    renderConversations();
    const conv = state.conversations.find((c) => c.id === id);
    if (conv) {
      titleEl.textContent = conv.title;
      metaEl.textContent = conv.participants.map(p => p.name).join(', ');
      const initial = (conv.title || 'U').trim().charAt(0).toUpperCase();
      const avatar = document.getElementById('chat-thread-avatar');
      if (avatar) avatar.textContent = initial;
    }
    emptyEl.style.display = 'none';
    threadEl.classList.remove('hidden');
    if (window.matchMedia('(max-width: 900px)').matches) {
      document.body.classList.add('chat-mobile-thread-open');
    }
    const messages = await fetchJson(`/chat/conversations/${id}`);
    renderMessages(messages);
    subscribeConversation(id);
    const lastId = messages.length ? messages[messages.length - 1].id : null;
    await fetchJson(`/chat/conversations/${id}/read`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ last_read_message_id: lastId }),
    });
  };

  const loadConversations = async () => {
    state.conversations = await fetchJson('/chat/conversations');
    renderConversations();
  };

  const collectParticipants = () => {
    return Array.from(document.querySelectorAll('#chat-people input[type="checkbox"]:checked'))
      .map((el) => parseInt(el.value, 10));
  };

  const createConversation = async () => {
    const type = document.querySelector('input[name="chat-type"]:checked')?.value || 'direct';
    const participantIds = collectParticipants();
    const payload = {
      type,
      name: type === 'group' ? (groupNameInput.value || null) : null,
      participant_ids: participantIds,
    };

    const response = await fetchJson('/chat/conversations', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });

    modal.classList.add('hidden');
    groupNameInput.value = '';
    document.querySelectorAll('#chat-people input[type="checkbox"]').forEach((el) => { el.checked = false; });
    await loadConversations();
    if (response.id) setActiveConversation(response.id);
  };

  formEl.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!state.activeId) return;
    const body = inputEl.value.trim();
    const files = attachEl.files;
    if (!body && (!files || files.length === 0)) return;

    const data = new FormData();
    if (body) data.append('body', body);
    if (state.replyTo) data.append('reply_to', state.replyTo.id);
    if (files && files.length) {
      Array.from(files).forEach((file) => data.append('attachments[]', file));
    }

    const message = await fetchJson(`/chat/conversations/${state.activeId}/messages`, {
      method: 'POST',
      body: data,
    });

    messagesEl.appendChild(renderMessage(message));
    messagesEl.scrollTop = messagesEl.scrollHeight;
    inputEl.value = '';
    attachEl.value = '';
    renderAttachmentPreview([]);
    clearReply();
  });

  emojiBtn.addEventListener('click', () => {
    state.emojiMode = 'input';
    state.reactionMessageId = null;
    toggleEmojiPicker(emojiPicker.classList.contains('hidden'));
  });

  emojiPicker.addEventListener('click', async (e) => {
    const btn = e.target.closest('.chat-emoji');
    if (!btn) return;
    const emoji = btn.dataset.emoji;
    if (state.emojiMode === 'reaction' && state.reactionMessageId) {
      await fetchJson(`/chat/messages/${state.reactionMessageId}/reactions`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ emoji }),
      });
    } else {
      inputEl.value = `${inputEl.value}${emoji}`;
      inputEl.focus();
    }
    toggleEmojiPicker(false);
    state.emojiMode = 'input';
    state.reactionMessageId = null;
  });

  replyCancelEl.addEventListener('click', clearReply);

  if (attachEl) {
    attachEl.addEventListener('change', (e) => {
      renderAttachmentPreview(e.target.files);
    });
  }
  modalOpenButtons.forEach((btn) => {
    btn.addEventListener('click', () => modal.classList.remove('hidden'));
  });
  modalClose.addEventListener('click', () => modal.classList.add('hidden'));
  modalCreate.addEventListener('click', createConversation);
  addPeopleBtn.addEventListener('click', () => modal.classList.remove('hidden'));
  if (backBtn) {
    backBtn.addEventListener('click', () => {
      document.body.classList.remove('chat-mobile-thread-open');
    });
  }

  renderEmojiPicker();
  loadConversations();
})();
