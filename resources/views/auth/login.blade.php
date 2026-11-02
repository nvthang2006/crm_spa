<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<main class="spa-login">
    <section class="spa-card spa-login-card">
        <div class="spa-login-brand">
            <span class="spa-brand-mark"><span class="material-symbols-outlined" aria-hidden="true">spa</span></span>
            <div><h1>{{ config('app.name') }}</h1><p>Hệ thống quản trị vận hành</p></div>
        </div>
        <div style="margin-bottom:22px">
            <h2 style="margin:0;font-size:20px">Chào mừng trở lại</h2>
            <p class="spa-muted spa-small" style="margin:6px 0 0">Đăng nhập bằng tài khoản quản trị viên.</p>
        </div>
        @if ($errors->any())
            <x-ui.alert variant="danger" class="mb-4">{{ $errors->first() }}</x-ui.alert>
        @endif
        <form method="POST" action="{{ route('login') }}" class="spa-form-stack">
            @csrf
            <x-ui.input label="Email quản trị" id="email" name="email" type="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-ui.input label="Mật khẩu" id="password" name="password" type="password" required autocomplete="current-password" />
            <x-ui.checkbox name="remember" label="Ghi nhớ đăng nhập" />
            <x-ui.button type="submit" icon="login">Đăng nhập</x-ui.button>
        </form>
        <a class="spa-small" href="{{ route('password.request') }}" style="display:block;margin-top:16px;text-align:center;color:#0f766e">Quên mật khẩu?</a>
        <p class="spa-muted spa-small" style="margin:22px 0 0;text-align:center">Tài khoản dev: admin@anmien.local / password</p>
    </section>
</main>
</body>
</html>
