<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="canonical" href="{{ url('/') }}">
    <meta name="robots" content="noindex, nofollow">
    @php
        $gscCode = preg_replace('/[^A-Za-z0-9_=\-]/', '', (string) \App\Models\Setting::get('google_search_console_verification'));
        $ga4Id   = preg_replace('/[^A-Za-z0-9_\-]/', '', (string) \App\Models\Setting::get('google_analytics_id'));
    @endphp
    @if($gscCode)
    <meta name="google-site-verification" content="{{ $gscCode }}">
    @endif
    @if($ga4Id)
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $ga4Id }}"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
      gtag('config', '{{ $ga4Id }}');
    </script>
    @endif
    <title>Coming Soon - {{ config('app.name', 'Jwellers') }}</title>

    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=poppins:300,400,500,600,700&display=swap" rel="stylesheet">

    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Poppins', sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #c9a227 0%, #6b531d 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
            color: #222;
        }
        .card {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.18);
            width: 100%;
            max-width: 440px;
            padding: 36px 32px;
            text-align: center;
        }
        .logo {
            height: 48px;
            margin-bottom: 20px;
        }
        h1 {
            font-size: 26px;
            font-weight: 700;
            color: #6b531d;
            margin-bottom: 6px;
        }
        .subtitle {
            font-size: 14px;
            color: #525252;
            margin-bottom: 28px;
            line-height: 1.5;
        }
        .waitlist-heading {
            font-size: 18px;
            font-weight: 600;
            color: #222;
            margin-bottom: 4px;
        }
        .waitlist-sub {
            font-size: 13px;
            color: #737373;
            margin-bottom: 18px;
        }
        label {
            display: block;
            font-size: 12px;
            font-weight: 500;
            color: #525252;
            margin-bottom: 6px;
            text-align: left;
        }
        .phone-group {
            display: flex;
            border: 1.5px solid #e5e5e5;
            border-radius: 10px;
            overflow: hidden;
            transition: border-color 0.15s;
            margin-bottom: 4px;
        }
        .phone-group:focus-within {
            border-color: #c9a227;
            box-shadow: 0 0 0 3px rgba(201,162,39,0.15);
        }
        .phone-prefix {
            padding: 12px 14px;
            background: #f5f5f5;
            font-size: 14px;
            font-weight: 600;
            color: #525252;
            border-right: 1.5px solid #e5e5e5;
        }
        .phone-input {
            flex: 1;
            padding: 12px 14px;
            font-size: 14px;
            border: none;
            outline: none;
            font-family: inherit;
            background: #fff;
            color: #222;
        }
        .phone-input::placeholder { color: #a3a3a3; }
        .btn-primary {
            width: 100%;
            padding: 13px;
            background: #7a1f2b;
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            transition: background 0.15s;
            margin-top: 12px;
        }
        .btn-primary:hover, .btn-primary:focus-visible {
            background: #5f1721;
            outline: none;
        }
        .btn-primary:focus-visible { box-shadow: 0 0 0 3px rgba(248,147,29,0.3); }
        .error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
            font-size: 13px;
            padding: 10px 12px;
            border-radius: 8px;
            margin-bottom: 14px;
            text-align: left;
        }
        .success {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #166534;
            font-size: 13px;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 14px;
            text-align: center;
            font-weight: 500;
        }
        .divider {
            margin: 24px 0 16px;
            border-top: 1px solid #e5e5e5;
            position: relative;
        }
        .preview-toggle {
            background: none;
            border: none;
            font-size: 12px;
            color: #737373;
            cursor: pointer;
            text-decoration: underline;
            font-family: inherit;
            padding: 4px;
        }
        .preview-toggle:hover { color: #6b531d; }
        .preview-box {
            margin-top: 12px;
            padding-top: 12px;
        }
        .preview-box input[type=password],
        .preview-box input[type=text] {
            width: 100%;
            padding: 11px 40px 11px 13px;
            font-size: 13px;
            border: 1.5px solid #e5e5e5;
            border-radius: 10px;
            outline: none;
            font-family: inherit;
            transition: border-color 0.15s;
        }
        .preview-box input[type=password]:focus,
        .preview-box input[type=text]:focus {
            border-color: #c9a227;
            box-shadow: 0 0 0 3px rgba(201,162,39,0.15);
        }
        .password-wrap { position: relative; }
        .password-toggle {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            padding: 6px;
            color: #737373;
            display: flex;
            align-items: center;
            border-radius: 6px;
        }
        .password-toggle:hover { color: #6b531d; background: #f5f5f5; }
        .password-toggle svg { width: 18px; height: 18px; }
        .btn-secondary {
            width: 100%;
            padding: 11px;
            background: #c9a227;
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            margin-top: 8px;
            transition: background 0.15s;
        }
        .btn-secondary:hover { background: #a9851f; }
        .footer-text {
            margin-top: 22px;
            font-size: 11px;
            color: #a3a3a3;
        }
        .footer-text a { color: #c9a227; text-decoration: none; }
        [hidden] { display: none !important; }
        @media (prefers-reduced-motion: reduce) {
            * { transition: none !important; }
        }
    </style>
</head>
<body>
    <main class="card">
        <img class="logo" src="{{ asset('images/colorlogo.png') }}" alt="Jwellers">

        <h1>Something Adorable is Coming Soon 💛</h1>
        <p class="subtitle">We're almost ready to bring you jewellery you'll love.</p>

        @if(session('waitlist_success'))
            <div class="success">Thanks! We'll notify you on launch day.</div>
        @endif

        <p class="waitlist-sub" style="margin-bottom: 18px; line-height: 1.6;">Sign up with your number to get early access, exclusive launch offers, and first pick of our collection.</p>

        <form method="POST" action="{{ route('prelaunch.signup') }}" novalidate>
            @csrf
            <label for="phone">Your mobile number</label>
            <div class="phone-group">
                <span class="phone-prefix">+91</span>
                <input
                    type="tel"
                    id="phone"
                    name="phone"
                    class="phone-input"
                    placeholder="10-digit mobile number"
                    pattern="[6-9][0-9]{9}"
                    inputmode="numeric"
                    maxlength="10"
                    required
                    value="{{ old('phone') }}"
                    aria-describedby="phone-error">
            </div>
            @error('phone')
                <p id="phone-error" class="error" style="margin-top: 8px;">{{ $message }}</p>
            @enderror
            <button type="submit" class="btn-primary">Claim My Early Bird Offer</button>
        </form>

        <div class="divider"></div>

        <div id="preview-wrap">
            <button type="button" class="preview-toggle" onclick="togglePreview()">Have a preview code?</button>
            <div class="preview-box" id="preview-box" hidden>
                <form method="POST" action="{{ route('prelaunch.verify') }}">
                    @csrf
                    <label for="password" class="sr-only">Preview code</label>
                    <div class="password-wrap">
                        <input type="password" id="password" name="password" placeholder="Enter preview code" autocomplete="off">
                        <button type="button" class="password-toggle" onclick="togglePasswordVisibility()" aria-label="Show password" id="pwToggle">
                            <svg id="eye-open" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            <svg id="eye-closed" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" hidden><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"/></svg>
                        </button>
                    </div>
                    @error('password')
                        <p class="error" style="margin-top: 8px;">{{ $message }}</p>
                    @enderror
                    <button type="submit" class="btn-secondary">Unlock Preview</button>
                </form>
            </div>
        </div>

        <p class="footer-text">
            <a href="{{ route('privacy') }}">Privacy Policy</a>
            &nbsp;&middot;&nbsp;
            <a href="{{ route('terms') }}">Terms of Service</a>
            &nbsp;&middot;&nbsp;
            <a href="{{ route('contact') }}">Contact</a>
        </p>
        <p class="footer-text" style="margin-top: 8px;">&copy; {{ date('Y') }} {{ config('app.name', 'Jwellers') }}. All rights reserved.</p>
    </main>

    <script>
        function togglePasswordVisibility() {
            var input = document.getElementById('password');
            var eyeOpen = document.getElementById('eye-open');
            var eyeClosed = document.getElementById('eye-closed');
            var btn = document.getElementById('pwToggle');
            if (input.type === 'password') {
                input.type = 'text';
                eyeOpen.setAttribute('hidden', '');
                eyeClosed.removeAttribute('hidden');
                btn.setAttribute('aria-label', 'Hide password');
            } else {
                input.type = 'password';
                eyeClosed.setAttribute('hidden', '');
                eyeOpen.removeAttribute('hidden');
                btn.setAttribute('aria-label', 'Show password');
            }
        }

        function togglePreview() {
            var box = document.getElementById('preview-box');
            var toggle = document.querySelector('.preview-toggle');
            if (box.hasAttribute('hidden')) {
                box.removeAttribute('hidden');
                toggle.textContent = 'Hide preview code';
                document.getElementById('password').focus();
            } else {
                box.setAttribute('hidden', '');
                toggle.textContent = 'Have a preview code?';
            }
        }
        @if($errors->has('password'))
            document.addEventListener('DOMContentLoaded', togglePreview);
        @endif
    </script>
</body>
</html>
