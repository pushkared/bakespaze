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
  const settingsBtn = document.getElementById('chat-settings');
  const groupModal = document.getElementById('chat-group-modal');
  const groupClose = document.getElementById('chat-group-close');
  const groupSave = document.getElementById('chat-group-save');
  const groupEditName = document.getElementById('chat-group-edit-name');
  const groupEditIcon = document.getElementById('chat-group-edit-icon');

  const state = {
    conversations: [],
    activeId: null,
    activeConversation: null,
    receipts: {
      readMap: {},
      deliveredMap: {},
    },
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

  const escapeHtml = (value) => {
    if (value == null) return '';
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/\"/g, '&quot;')
      .replace(/'/g, '&#39;');
  };

  const linkify = (text) => {
    const safe = escapeHtml(text);
    return safe.replace(/https?:\/\/[^\s<]+/g, (raw) => {
      const cleaned = raw.replace(/[),.]+$/, '');
      return `<a href="${cleaned}" class="chat-link" target="_blank" rel="noopener noreferrer">${cleaned}</a>${raw.slice(cleaned.length)}`;
    });
  };

  const renderMessage = (message) => {
    const wrapper = document.createElement('div');
    wrapper.className = `chat-msg ${message.user_id === config.userId ? 'self' : ''}`;
    wrapper.dataset.id = message.id;
    if (message.temp_id) wrapper.dataset.tempId = message.temp_id;

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
                <a class="chat-attachment-thumb" href="${previewUrl}" data-preview="image" rel="noopener noreferrer">
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
              <a href="${url}" data-preview="file" data-name="${name}" rel="noopener noreferrer">Open</a>
            </div>
          `;
        }).join('')}</div>`
      : '';

    const isGroup = state.activeConversation?.type === 'group';
    const senderName = message.user_id === config.userId ? 'You' : (message.sender || 'Member');
    const senderAvatar = message.user_id === config.userId ? config.userAvatar : message.sender_avatar;
    const senderInitial = (senderName || 'U').trim().charAt(0).toUpperCase();
    const senderHtml = isGroup ? `
      <div class="chat-msg-sender">
        <div class="avatar">${senderAvatar ? `<img src="${senderAvatar}" alt="${senderName}">` : senderInitial}</div>
        <span>${senderName}</span>
      </div>
    ` : '';

    const status = message.status || (message.user_id === config.userId ? 'sent' : '');
    const statusHtml = message.user_id === config.userId ? `
      <div class="chat-msg-status ${status === 'seen' ? 'seen' : ''}" data-status="${status}">
        <span class="tick">${status === 'sent' ? '✓' : '✓✓'}</span>
      </div>
    ` : '';

    const bodyHtml = message.body ? `<div class="chat-bubble">${linkify(message.body)}</div>` : '';
    const linkMatch = message.body ? message.body.match(/https?:\/\/[^\s<]+/) : null;
    const previewUrl = linkMatch ? linkMatch[0].replace(/[),.]+$/, '') : '';
    const previewHtml = previewUrl ? `
      <div class="chat-link-preview" data-url="${previewUrl}">
        <div class="chat-link-preview__meta">Loading preview...</div>
      </div>
    ` : '';

    wrapper.innerHTML = `
      <div class="chat-msg-time">${formatTime(message.created_at)}</div>
      <div class="chat-msg-body">
        ${senderHtml}
        ${message.reply_to ? `<div class="chat-reply">${message.reply_to.sender || ''}: ${message.reply_to.body || 'Attachment'}</div>` : ''}
        ${bodyHtml}
        ${previewHtml}
        ${attachmentsHtml}
        ${statusHtml}
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

    const preview = wrapper.querySelector('.chat-link-preview');
    if (preview) {
      const url = preview.dataset.url;
      if (url) {
        fetchJson(`/chat/link-preview?url=${encodeURIComponent(url)}`)
          .then((data) => {
            preview.innerHTML = `
              <a class="chat-link-preview__inner" href="${data.url}" target="_blank" rel="noopener noreferrer">
                ${data.image ? `<img src="${data.image}" alt="${data.title || 'Link'}">` : ''}
                <div class="chat-link-preview__text">
                  <div class="chat-link-preview__title">${escapeHtml(data.title || data.url)}</div>
                  ${data.description ? `<div class="chat-link-preview__desc">${escapeHtml(data.description)}</div>` : ''}
                  <div class="chat-link-preview__url">${escapeHtml(data.url)}</div>
                </div>
              </a>
            `;
          })
          .catch(() => {
            preview.remove();
          });
      }
    }

    return wrapper;
  };

  const computeStatus = (messageId) => {
    if (!state.activeConversation) return 'sent';
    const others = (state.activeConversation.participants || []).filter(p => p.id !== config.userId);
    if (!others.length) return 'sent';
    const deliveredAll = others.every(p => (state.receipts.deliveredMap[p.id] || 0) >= messageId);
    const seenAll = others.every(p => (state.receipts.readMap[p.id] || 0) >= messageId);
    if (seenAll) return 'seen';
    if (deliveredAll) return 'delivered';
    return 'sent';
  };

  const updateStatuses = () => {
    const nodes = messagesEl ? messagesEl.querySelectorAll('.chat-msg.self') : [];
    nodes.forEach((node) => {
      const id = parseInt(node.dataset.id, 10);
      if (!id) return;
      const status = computeStatus(id);
      const statusEl = node.querySelector('.chat-msg-status');
      if (!statusEl) return;
      statusEl.dataset.status = status;
      statusEl.classList.toggle('seen', status === 'seen');
      const tick = statusEl.querySelector('.tick');
      if (tick) tick.textContent = status === 'sent' ? '✓' : '✓✓';
    });
  };

  const renderMessages = (messages) => {
    messagesEl.innerHTML = '';
    messages.forEach((message) => {
      messagesEl.appendChild(renderMessage(message));
    });
    messagesEl.scrollTop = messagesEl.scrollHeight;
    updateStatuses();
  };

  const downloadFile = async (url, filename) => {
    try {
      const res = await fetch(url, { credentials: 'include' });
      if (!res.ok) throw new Error('Download failed');
      const blob = await res.blob();
      const link = document.createElement('a');
      link.href = URL.createObjectURL(blob);
      link.download = filename || 'attachment';
      document.body.appendChild(link);
      link.click();
      link.remove();
      setTimeout(() => URL.revokeObjectURL(link.href), 500);
    } catch (err) {
      window.open(url, '_blank', 'noopener,noreferrer');
    }
  };

  const openPreview = (url, type = 'image', name = '') => {
    let overlay = document.getElementById('chat-preview-overlay');
    if (!overlay) {
      overlay = document.createElement('div');
      overlay.id = 'chat-preview-overlay';
      overlay.className = 'chat-preview-overlay';
      overlay.innerHTML = `
        <div class="chat-preview-card">
          <div class="chat-preview-actions">
            <button type="button" class="chat-preview-close" aria-label="Close">Close</button>
            <button type="button" class="chat-preview-download" aria-label="Download">Download</button>
          </div>
          <img class="chat-preview-image" alt="Attachment preview" hidden>
          <iframe class="chat-preview-frame" title="Attachment preview" hidden></iframe>
        </div>
      `;
      document.body.appendChild(overlay);
      overlay.addEventListener('click', (e) => {
        if (e.target === overlay) overlay.classList.remove('open');
      });
      overlay.querySelector('.chat-preview-close')?.addEventListener('click', () => {
        overlay.classList.remove('open');
      });
      overlay.querySelector('.chat-preview-download')?.addEventListener('click', () => {
        const link = overlay.dataset.url || '';
        const fname = overlay.dataset.name || 'attachment';
        if (link) downloadFile(link, fname);
      });
    }
    overlay.dataset.url = url;
    overlay.dataset.name = name || '';
    const img = overlay.querySelector('.chat-preview-image');
    const frame = overlay.querySelector('.chat-preview-frame');
    if (img) img.hidden = type !== 'image';
    if (frame) frame.hidden = type === 'image';
    if (type === 'image' && img) {
      img.src = url;
    } else if (frame) {
      frame.src = url;
    }
    overlay.classList.add('open');
  };

  if (messagesEl) {
    messagesEl.addEventListener('click', (e) => {
      const link = e.target.closest('a[data-preview="image"]');
      if (link) {
        const url = link.getAttribute('href');
        if (!url) return;
        e.preventDefault();
        openPreview(url, 'image');
        return;
      }
      const fileLink = e.target.closest('a[data-preview="file"]');
      if (!fileLink) return;
      const fileUrl = fileLink.getAttribute('href');
      if (!fileUrl) return;
      e.preventDefault();
      openPreview(fileUrl, 'file', fileLink.dataset.name || '');
    });
  }

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
        if (message.user_id !== config.userId) {
          fetchJson(`/chat/conversations/${state.activeId}/delivered`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ last_delivered_message_id: message.id }),
          });
          fetchJson(`/chat/conversations/${state.activeId}/read`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ last_read_message_id: message.id }),
          });
        }
      });
    state.echo.private(state.channelName)
      .listen('.message.receipt', (event) => {
        if (event.conversation_id !== state.activeId) return;
        state.receipts.readMap[event.user_id] = event.last_read_message_id || 0;
        state.receipts.deliveredMap[event.user_id] = event.last_delivered_message_id || 0;
        updateStatuses();
      });
  };

  const setActiveConversation = async (id) => {
    state.activeId = id;
    renderConversations();
    const conv = state.conversations.find((c) => c.id === id);
    state.activeConversation = conv || null;
    if (conv) {
      titleEl.textContent = conv.title;
      metaEl.textContent = conv.participants.map(p => p.name).join(', ');
      const initial = (conv.title || 'U').trim().charAt(0).toUpperCase();
      const avatar = document.getElementById('chat-thread-avatar');
      if (avatar) {
        const peer = conv.type === 'direct'
          ? conv.participants.find((p) => p.id !== config.userId)
          : null;
        const imageUrl = conv.type === 'group'
          ? (conv.avatar_url || '')
          : (peer?.avatar_url || '');
        if (imageUrl) {
          avatar.textContent = '';
          avatar.style.backgroundImage = `url("${imageUrl}")`;
          avatar.classList.add('has-image');
        } else {
          avatar.textContent = initial;
          avatar.style.backgroundImage = '';
          avatar.classList.remove('has-image');
        }
      }
    }
    if (settingsBtn) {
      settingsBtn.style.display = conv?.type === 'group' ? 'inline-flex' : 'none';
    }
    emptyEl.style.display = 'none';
    threadEl.classList.remove('hidden');
    if (window.matchMedia('(max-width: 900px)').matches) {
      document.body.classList.add('chat-mobile-thread-open');
    }
    const response = await fetchJson(`/chat/conversations/${id}`);
    const messages = response.messages || response;
    state.receipts.readMap = response.read_map || {};
    state.receipts.deliveredMap = response.delivered_map || {};
    renderMessages(messages);
    subscribeConversation(id);
    const lastId = messages.length ? messages[messages.length - 1].id : null;
    const lastFromOthers = messages.filter(m => m.user_id !== config.userId).slice(-1)[0];
    if (lastFromOthers?.id) {
      await fetchJson(`/chat/conversations/${id}/delivered`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ last_delivered_message_id: lastFromOthers.id }),
      });
    }
    await fetchJson(`/chat/conversations/${id}/read`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ last_read_message_id: lastId }),
    });
  };

  const openFromQuery = async () => {
    if (state.activeId) return;
    const params = new URLSearchParams(window.location.search);
    const rawId = params.get('conversation');
    const conversationId = rawId ? parseInt(rawId, 10) : null;
    if (conversationId) {
      const match = state.conversations.find((c) => c.id === conversationId);
      if (match) {
        await setActiveConversation(match.id);
        return;
      }
    }

    const rawUser = params.get('user');
    const userId = rawUser ? parseInt(rawUser, 10) : null;
    if (!userId || userId === config.userId) return;
    const directMatch = state.conversations.find((c) => c.type === 'direct' && c.participants.some((p) => p.id === userId));
    if (directMatch) {
      await setActiveConversation(directMatch.id);
      return;
    }
    try {
      const response = await fetchJson('/chat/conversations', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ type: 'direct', participant_ids: [userId] }),
      });
      await loadConversations();
      if (response.id) {
        await setActiveConversation(response.id);
      }
    } catch (err) {
      // Ignore failures to avoid breaking chat load.
    }
  };

  const loadConversations = async () => {
    state.conversations = await fetchJson('/chat/conversations');
    renderConversations();
    await openFromQuery();
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

    const tempId = `tmp-${Date.now()}`;
    const tempMessage = {
      id: tempId,
      temp_id: tempId,
      conversation_id: state.activeId,
      user_id: config.userId,
      sender: config.userName,
      sender_avatar: config.userAvatar,
      body: body || null,
      created_at: new Date().toISOString(),
      attachments: [],
      reactions: [],
      status: 'sent',
    };
    messagesEl.appendChild(renderMessage(tempMessage));
    messagesEl.scrollTop = messagesEl.scrollHeight;

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

    const tempEl = messagesEl.querySelector(`[data-temp-id="${tempId}"]`);
    const finalEl = renderMessage(message);
    if (tempEl) {
      tempEl.replaceWith(finalEl);
    } else {
      messagesEl.appendChild(finalEl);
    }
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
  if (settingsBtn) {
    settingsBtn.addEventListener('click', () => {
      if (!state.activeConversation || state.activeConversation.type !== 'group') return;
      if (groupEditName) groupEditName.value = state.activeConversation.title || '';
      if (groupEditIcon) groupEditIcon.value = '';
      if (groupModal) groupModal.classList.remove('hidden');
    });
  }
  if (groupClose) groupClose.addEventListener('click', () => groupModal && groupModal.classList.add('hidden'));
  if (groupSave) {
    groupSave.addEventListener('click', async () => {
      if (!state.activeConversation) return;
      const formData = new FormData();
      formData.append('name', groupEditName?.value || '');
      if (groupEditIcon && groupEditIcon.files && groupEditIcon.files[0]) {
        formData.append('avatar', groupEditIcon.files[0]);
      }
      const response = await fetchJson(`/chat/conversations/${state.activeConversation.id}`, {
        method: 'POST',
        body: formData,
      });
      state.activeConversation.title = response.name || state.activeConversation.title;
      state.activeConversation.avatar_url = response.avatar_url || state.activeConversation.avatar_url;
      titleEl.textContent = state.activeConversation.title;
      const avatar = document.getElementById('chat-thread-avatar');
      if (avatar && state.activeConversation.avatar_url) {
        avatar.textContent = '';
        avatar.style.backgroundImage = `url("${state.activeConversation.avatar_url}")`;
        avatar.classList.add('has-image');
      }
      await loadConversations();
      if (groupModal) groupModal.classList.add('hidden');
    });
  }
  if (backBtn) {
    backBtn.addEventListener('click', () => {
      document.body.classList.remove('chat-mobile-thread-open');
    });
  }

  renderEmojiPicker();
  loadConversations();
})();
