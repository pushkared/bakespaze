(() => {
  if (!('serviceWorker' in navigator)) return;
  const vapidKeyMeta = document.querySelector('meta[name="vapid-public-key"]');
  const vapidPublicKey = vapidKeyMeta ? vapidKeyMeta.getAttribute('content') : '';
  if (!vapidPublicKey) return;

  const urlBase64ToUint8Array = (base64String) => {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const raw = atob(base64);
    const output = new Uint8Array(raw.length);
    for (let i = 0; i < raw.length; i++) output[i] = raw.charCodeAt(i);
    return output;
  };

  const subscribeUrl = '/notifications/subscribe';
  const unsubscribeUrl = '/notifications/subscribe';
  const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

  const sendSubscription = (subscription) => {
    return fetch(subscribeUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': token || '',
      },
      body: JSON.stringify(subscription),
      credentials: 'same-origin',
    });
  };

  const syncSubscription = async () => {
    const registration = await navigator.serviceWorker.ready;
    const subscription = await registration.pushManager.getSubscription();
    if (subscription) {
      await sendSubscription(subscription.toJSON());
      return;
    }

    const newSubscription = await registration.pushManager.subscribe({
      userVisibleOnly: true,
      applicationServerKey: urlBase64ToUint8Array(vapidPublicKey),
    });

    await sendSubscription(newSubscription.toJSON());
  };

  const init = async () => {
    try {
      const permission = await Notification.requestPermission();
      if (permission !== 'granted') return;
      await syncSubscription();
    } catch (err) {
      console.error('Push subscription failed:', err);
    }
  };

  window.addEventListener('load', init);
})();
