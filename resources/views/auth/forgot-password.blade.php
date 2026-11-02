<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quên mật khẩu · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<main class="spa-login">
    <section class="spa-card spa-login-card">
        <div class="spa-login-brand">
            <span class="spa-brand-mark"><span class="material-symbols-outlined" aria-hidden="true">spa</span></span>
            <div><h1>Đặt lại mật khẩu</h1><p>Khôi phục tài khoản quản trị {{ config('app.name') }}</p></div>
        </div>
        @if ($errors->any())
            <x-ui.alert variant="danger" class="mb-4">{{ $errors->first() }}</x-ui.alert>
        @endif
        @if (session('status'))
            <x-ui.alert variant="success" class="mb-4">{{ session('status') }}</x-ui.alert>
        @endif
        <form method="POST" action="{{ route('password.email') }}" class="spa-form-stack">
            @csrf
            <x-ui.input label="Email quản trị" id="email" name="email" type="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-ui.button type="submit">Gửi liên kết khôi phục</x-ui.button>
        </form>
        <a class="spa-small" href="{{ route('login') }}" style="display:block;margin-top:18px;color:#0f766e">← Quay lại đăng nhập</a>
    </section>
</main>
</body>
</html>
