<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đặt lại mật khẩu · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<main class="spa-login">
    <section class="spa-card spa-login-card">
        <div class="spa-login-brand">
            <span class="spa-brand-mark"><span class="material-symbols-outlined" aria-hidden="true">spa</span></span>
            <div><h1>Đặt lại mật khẩu</h1><p>Tạo mật khẩu mới cho tài khoản quản trị</p></div>
        </div>
        @if ($errors->any())
            <x-ui.alert variant="danger" class="mb-4">{{ $errors->first() }}</x-ui.alert>
        @endif
        <form method="POST" action="{{ route('password.update') }}" class="spa-form-stack">
            @csrf
            <input type="hidden" name="token" value="{{ $request->route('token') }}">
            <x-ui.input label="Email" id="email" name="email" type="email" :value="old('email', $request->email)" required autofocus autocomplete="username" />
            <x-ui.input label="Mật khẩu mới" id="password" name="password" type="password" required autocomplete="new-password" />
            <x-ui.input label="Nhập lại mật khẩu" id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" />
            <x-ui.button type="submit">Lưu mật khẩu mới</x-ui.button>
        </form>
    </section>
</main>
</body>
</html>
