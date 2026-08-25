@extends('layouts.adminlte.app')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')
    <div class="row">
        <div class="col-lg-3 col-6">
            <div class="small-box bg-info">
                <div class="inner">
                    <h3>{{ \App\Models\User::count() }}</h3>
                    <p>Pengguna</p>
                </div>
                <div class="icon"><i class="fas fa-users"></i></div>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="small-box bg-success">
                <div class="inner">
                    <h3>{{ \App\Models\Role::count() }}</h3>
                    <p>Role</p>
                </div>
                <div class="icon"><i class="fas fa-user-shield"></i></div>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="small-box bg-warning">
                <div class="inner">
                    <h3>{{ DB::table('forms')->count() }}</h3>
                    <p>Form</p>
                </div>
                <div class="icon"><i class="fas fa-wpforms"></i></div>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="small-box bg-danger">
                <div class="inner">
                    <h3>{{ DB::table('reports')->count() }}</h3>
                    <p>Report</p>
                </div>
                <div class="icon"><i class="fas fa-chart-bar"></i></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3 class="card-title">Tahap Pengembangan</h3></div>
        <div class="card-body">
            <p class="text-muted mb-2">
                Tahap 1 (layout + login + sidebar dinamis) sudah aktif.
                Menu di sidebar dibaca dari tabel <code>menus</code> dan disaring
                berdasarkan <code>permission_code</code>.
            </p>
            <p class="text-muted mb-0">
                Menu yang route-nya belum dibuat sengaja mengarah ke <code>#</code>
                agar sidebar tetap utuh sampai controller-nya menyusul.
            </p>
        </div>
    </div>
@endsection
