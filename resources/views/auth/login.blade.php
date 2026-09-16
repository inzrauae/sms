<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Sign in — {{ config('portal.brand_name') }}</title>
<link rel="stylesheet" href="{{ asset('css/base.css') }}">
<link rel="stylesheet" href="{{ asset('css/site.css') }}">
</head>
<body>
<div class="auth-page">

  <aside class="auth-aside">
    <a class="wordmark" href="/" style="color:#fff">
      <span class="glyph" style="background:#2563eb">eS</span>
      <span data-brand>{{ config('portal.brand_name') }}</span>
    </a>

    <div>
      <h2>Ten credits are waiting on your account.</h2>
      <p>Enough to send a test message to your own phone and watch the receipt come back.</p>

      <div class="receipt">
        <div><span>Message</span><span>Your OTP is 4821</span></div>
        <div><span>To</span><span>9471000••••</span></div>
        <div><span>Segments</span><span>1</span></div>
        <div><span>Status</span><span>Delivered</span></div>
      </div>
    </div>

    <p style="font-size:0.84rem">Dialog, Mobitel, Hutch and Airtel.</p>
  </aside>

  <main class="auth-main">
    <div class="auth-card">

      <!-- Sign in -->
      <section id="view-login">
        <h1>Sign in</h1>
        <p class="sub">Welcome back.</p>
        <div class="form-error" id="login-error" role="alert" hidden></div>

        <form id="form-login" novalidate>
          <label class="field">
            <span>Email</span>
            <input class="input" type="email" name="email" autocomplete="email" required>
          </label>
          <label class="field">
            <span>Password</span>
            <input class="input" type="password" name="password" autocomplete="current-password" required>
          </label>
          <button class="btn btn-block" type="submit">Sign in</button>
        </form>

        <p class="auth-switch">
          No account yet? <a href="#" data-view="register">Create one</a>
        </p>
      </section>

      <!-- Register -->
      <section id="view-register" hidden>
        <h1>Create your account</h1>
        <p class="sub">Ten free credits, no card needed.</p>
        <div class="form-error" id="register-error" role="alert" hidden></div>

        <form id="form-register" novalidate>
          <label class="field">
            <span>Your name</span>
            <input class="input" name="name" autocomplete="name" required>
          </label>
          <label class="field">
            <span>Business name <span class="hint">optional</span></span>
            <input class="input" name="company" autocomplete="organization">
          </label>
          <div class="row">
            <label class="field">
              <span>Email</span>
              <input class="input" type="email" name="email" autocomplete="email" required>
            </label>
            <label class="field">
              <span>Mobile</span>
              <input class="input" name="phone" inputmode="tel" placeholder="0712345678">
            </label>
          </div>
          <label class="field">
            <span>Password <span class="hint">8 characters or more</span></span>
            <input class="input" type="password" name="password" autocomplete="new-password" required>
          </label>
          <button class="btn btn-block" type="submit">Create account</button>
        </form>

        <p class="auth-switch">
          Already registered? <a href="#" data-view="login">Sign in</a>
        </p>
      </section>

    </div>
  </main>
</div>

<script>
(function () {
  var csrfToken = document.querySelector('meta[name="csrf-token"]').content;
  var views = {
    login: document.getElementById('view-login'),
    register: document.getElementById('view-register')
  };

  function show(name) {
    views.login.hidden = name !== 'login';
    views.register.hidden = name !== 'register';
    history.replaceState(null, '', name === 'register' ? '/register' : '/login');
    var first = views[name].querySelector('input');
    if (first) first.focus();
  }

  document.addEventListener('click', function (event) {
    var trigger = event.target.closest('[data-view]');
    if (!trigger) return;
    event.preventDefault();
    show(trigger.dataset.view);
  });

  if (location.pathname === '/register') show('register');

  function nextUrl(role) {
    var params = new URLSearchParams(location.search);
    return params.get('next') || (role === 'admin' ? '/console' : '/dashboard');
  }

  function wire(formId, errorId, endpoint) {
    var form = document.getElementById(formId);
    var error = document.getElementById(errorId);

    form.addEventListener('submit', async function (event) {
      event.preventDefault();
      error.hidden = true;

      var button = form.querySelector('button[type=submit]');
      var label = button.textContent;
      button.disabled = true;
      button.textContent = 'Working…';

      try {
        var body = Object.fromEntries(new FormData(form).entries());
        var response = await fetch(endpoint, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
          credentials: 'same-origin',
          body: JSON.stringify(body)
        });
        var payload = await response.json();

        if (!response.ok) {
          error.textContent = payload.message || 'That did not work. Check the details and try again.';
          error.hidden = false;
          return;
        }
        location.href = nextUrl(payload.data && payload.data.role);
      } catch (err) {
        error.textContent = 'Cannot reach the server. Check your connection and try again.';
        error.hidden = false;
      } finally {
        button.disabled = false;
        button.textContent = label;
      }
    });
  }

  wire('form-login', 'login-error', '/auth/login');
  wire('form-register', 'register-error', '/auth/register');

  fetch('/config').then(function (r) { return r.json(); }).then(function (payload) {
    if (payload.status !== 'success') return;
    Array.prototype.forEach.call(document.querySelectorAll('[data-brand]'), function (el) {
      el.textContent = payload.data.brand_name;
    });
    document.title = 'Sign in — ' + payload.data.brand_name;
  }).catch(function () {});
})();
</script>
</body>
</html>
