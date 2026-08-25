@extends('layouts.adminlte.app')
@section('title', 'Rincian Log')
@section('page-title', 'Rincian Log #'.$log->id)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('activity-logs.index') }}">Log Aktivitas</a></li>
    <li class="breadcrumb-item active">#{{ $log->id }}</li>
@endsection

@php
    $old = $log->old_values ?? [];
    $new = $log->new_values ?? [];
    $keys = array_values(array_unique([...array_keys($old), ...array_keys($new)]));
    sort($keys);
    $show = fn ($v) => $v === null ? null : (is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE));
@endphp

@section('content')
<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Keterangan</h3></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <tbody>
                        <tr><th style="width:120px">Waktu</th>
                            <td>{{ $log->created_at?->format('d/m/Y H:i:s') }}</td></tr>
                        <tr><th>Pengguna</th>
                            <td>
                                @if ($log->user)
                                    {{ $log->user->name }}
                                    <div class="text-muted small">{{ $log->user->email }}</div>
                                @else
                                    <span class="text-muted">sistem</span>
                                @endif
                            </td></tr>
                        <tr><th>Aksi</th>
                            <td>
                                <span class="badge badge-{{ ['create' => 'success', 'update' => 'info', 'delete' => 'danger'][$log->event] ?? 'secondary' }}">
                                    {{ $log->event }}
                                </span>
                            </td></tr>
                        <tr><th>Tabel</th><td><code>{{ $log->table_name ?: '—' }}</code></td></tr>
                        <tr><th>ID Baris</th><td>{{ $log->record_id ?: '—' }}</td></tr>
                        <tr><th>Modul</th><td>{{ $log->module ?: '—' }}</td></tr>
                        <tr><th>IP</th><td>{{ $log->ip_address ?: '—' }}</td></tr>
                        <tr><th>Metode</th><td>{{ $log->http_method ?: '—' }}</td></tr>
                        <tr><th>URL</th><td class="small text-break">{{ $log->url ?: '—' }}</td></tr>
                        <tr><th>Peramban</th><td class="small text-muted text-break">{{ $log->user_agent ?: '—' }}</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="card-footer">
                <a href="{{ route('activity-logs.index') }}" class="btn btn-default btn-sm">Kembali</a>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Perubahan Nilai</h3>
                @if ($changed)
                    <div class="card-tools">
                        <span class="badge badge-info">{{ count($changed) }} kolom berubah</span>
                    </div>
                @endif
            </div>
            <div class="card-body p-0">
                @if ($keys === [])
                    <p class="text-muted text-center py-4 mb-0">
                        Aksi ini tidak menyimpan nilai apa pun.
                    </p>
                @else
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th style="width:180px">Kolom</th>
                                <th>Nilai Lama</th>
                                <th>Nilai Baru</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach ($keys as $key)
                            @php $isChanged = in_array($key, $changed, true); @endphp
                            <tr class="{{ $isChanged ? 'table-warning' : '' }}">
                                <td>
                                    <code>{{ $key }}</code>
                                    @if ($isChanged)<i class="fas fa-pen text-warning small ml-1"></i>@endif
                                </td>
                                <td class="small text-break">
                                    @if ($show($old[$key] ?? null) === null)
                                        <span class="text-muted">—</span>
                                    @else
                                        {{ $show($old[$key]) }}
                                    @endif
                                </td>
                                <td class="small text-break">
                                    @if ($show($new[$key] ?? null) === null)
                                        <span class="text-muted">—</span>
                                    @else
                                        {{ $show($new[$key]) }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
            @if ($keys !== [])
                <div class="card-footer text-muted small">
                    Baris bertanda kuning adalah kolom yang nilainya berubah. Nilai lama
                    kosong pada aksi <code>create</code>; nilai baru kosong pada
                    <code>delete</code>.
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
