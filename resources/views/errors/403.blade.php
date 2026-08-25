<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Akses Ditolak</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/css/adminlte.min.css">
</head>
<body class="hold-transition">
<div class="error-page mt-5">
    <h2 class="headline text-warning">403</h2>
    <div class="error-content">
        <h3><i class="fas fa-exclamation-triangle text-warning mr-2"></i>Akses ditolak</h3>
        <p>{{ $exception?->getMessage() ?: 'Anda tidak memiliki izin untuk mengakses halaman ini.' }}</p>
        <a href="{{ url('/dashboard') }}" class="btn btn-primary btn-sm">Kembali ke dashboard</a>
    </div>
</div>
</body>
</html>
