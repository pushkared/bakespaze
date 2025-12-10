<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bakespaze</title>
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="#0b0b0b">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <link rel="apple-touch-icon" href="/images/icon-180.png">
    <link rel="stylesheet" href="/css/welcome.css">
</head>
<body>

<div class="screen-container">
    <div class="button-wrapper">
        <a href="auth/google/redirect" class="google-btn">
            <img src="/images/google-icon.svg" alt="Google">
            <span>Continue with Google</span>
        </a>
    </div>
</div>

<script>
  if ('serviceWorker' in navigator) {
    const isLocalhost = ['localhost', '127.0.0.1'].includes(window.location.hostname);
    const isSecure = window.location.protocol === 'https:';
    if (isLocalhost || isSecure) {
      window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch((err) => {
          console.error('Service worker registration failed:', err);
        });
      });
    }
  }
</script>
</body>
</html>
