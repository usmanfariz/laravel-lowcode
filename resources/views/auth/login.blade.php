<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <script>
        // Terapkan mode gelap sebelum render, supaya halaman tidak berkedip putih.
        (function () {
            try {
                if (localStorage.getItem('lc-theme') === 'dark') {
                    document.documentElement.setAttribute('data-lc-theme', 'dark');
                }
            } catch (e) { /* localStorage diblokir: biarkan mode terang. */ }
        })();
    </script>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk &mdash; {{ setting('app_name', config('app.name')) }}</title>
    <link rel="icon" href="{{ setting_file('app_favicon') ?: asset('favicon.ico') }}">
    <link rel="stylesheet" href="https://fonts.bunny.net/css?family=inter:400,500,600,700">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="{{ asset('css/theme.css') }}?v={{ @filemtime(public_path('css/theme.css')) }}">
</head>
<body class="hold-transition login-page">
<div class="login-box">
    <div class="login-logo">
        @if ($logo = setting_file('app_logo'))
            <img src="{{ $logo }}" alt="Logo" class="d-block mx-auto mb-2" style="max-height: 72px">
        @endif
        <b>{{ setting('app_name', config('app.name')) }}</b>
        @if ($perusahaan = setting('company_name'))
            <div class="text-muted" style="font-size: 14px">{{ $perusahaan }}</div>
        @endif
    </div>

    <div class="card">
        <div class="card-body login-card-body">
            <p class="login-box-msg">Masuk untuk memulai sesi</p>

            <form method="POST" action="{{ route('login.store') }}">
                @csrf

                <div class="input-group mb-3">
                    <input type="text" name="username" value="{{ old('username') }}"
                           class="form-control @error('username') is-invalid @enderror"
                           placeholder="Username atau email" required autofocus>
                    <div class="input-group-append">
                        <div class="input-group-text"><span class="fas fa-user"></span></div>
                    </div>
                    @error('username')
                        <span class="invalid-feedback d-block">{{ $message }}</span>
                    @enderror
                </div>

                <div class="input-group mb-3">
                    <input type="password" name="password"
                           class="form-control @error('password') is-invalid @enderror"
                           placeholder="Password" required>
                    <div class="input-group-append">
                        <div class="input-group-text"><span class="fas fa-lock"></span></div>
                    </div>
                    @error('password')
                        <span class="invalid-feedback d-block">{{ $message }}</span>
                    @enderror
                </div>

                <div class="row">
                    <div class="col-7">
                        <div class="icheck-primary">
                            <input type="checkbox" name="remember" id="remember" value="1">
                            <label for="remember">Ingat saya</label>
                        </div>
                    </div>
                    <div class="col-5">
                        <button type="submit" class="btn btn-primary btn-block">Masuk</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/js/adminlte.min.js"></script>
</body>
</html>
