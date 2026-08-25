@extends('layouts.adminlte.app')
@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')
@if ($widgets->isEmpty())
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="fas fa-th-large fa-3x text-muted mb-3"></i>
            <h5>Dashboard masih kosong</h5>
            <p class="text-muted mb-3">
                Widget dashboard diatur lewat metadata, sama seperti form dan report.
            </p>
            @can('system.dashboard')
                <a href="{{ route('builder.dashboard.index') }}" class="btn btn-primary">
                    <i class="fas fa-plus mr-1"></i> Atur Dashboard
                </a>
            @endcan
        </div>
    </div>
@else
    <div class="row">
        @foreach ($widgets as $widget)
            @php $isi = $data[$widget->id] ?? []; @endphp

            <div class="{{ $widget->columnClass() }}">
                @if (! empty($isi['error']))
                    {{-- Satu widget bermasalah tidak boleh mengosongkan dashboard. --}}
                    <div class="card card-outline card-warning">
                        <div class="card-body">
                            <h6 class="text-muted">{{ $widget->title }}</h6>
                            <p class="small text-warning mb-0">
                                <i class="fas fa-exclamation-triangle mr-1"></i>{{ $isi['error'] }}
                            </p>
                        </div>
                    </div>

                @elseif ($widget->type === 'stat')
                    <div class="small-box bg-{{ $widget->color }}">
                        <div class="inner">
                            <h3>{{ $dashboard->formatValue($widget, $isi['value'] ?? 0) }}</h3>
                            <p>{{ $widget->title }}</p>
                        </div>
                        @if ($widget->icon)
                            <div class="icon"><i class="{{ $widget->icon }}"></i></div>
                        @endif
                        @if ($widget->link_url)
                            <a href="{{ $widget->link_url }}" class="small-box-footer">
                                Lihat detail <i class="fas fa-arrow-circle-right"></i>
                            </a>
                        @endif
                    </div>

                @elseif ($widget->type === 'chart')
                    <div class="card card-outline card-{{ $widget->color }}">
                        <div class="card-header">
                            <h3 class="card-title">
                                @if ($widget->icon)<i class="{{ $widget->icon }} mr-1"></i>@endif
                                {{ $widget->title }}
                            </h3>
                            <div class="card-tools">
                                <a href="{{ url('reports/'.$widget->report_code) }}" class="btn btn-tool" title="Buka report">
                                    <i class="fas fa-external-link-alt"></i>
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <div style="position:relative; height:260px">
                                <canvas id="wchart-{{ $widget->id }}"
                                        data-chart="{{ json_encode($isi['chart']) }}"
                                        data-type="{{ $isi['report']->chart_type ?: 'bar' }}"></canvas>
                            </div>
                        </div>
                    </div>

                @elseif ($widget->type === 'table')
                    <div class="card card-outline card-{{ $widget->color }}">
                        <div class="card-header">
                            <h3 class="card-title">
                                @if ($widget->icon)<i class="{{ $widget->icon }} mr-1"></i>@endif
                                {{ $widget->title }}
                            </h3>
                            <div class="card-tools">
                                <a href="{{ url('reports/'.$widget->report_code) }}" class="btn btn-tool" title="Buka report">
                                    <i class="fas fa-external-link-alt"></i>
                                </a>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-sm table-striped mb-0">
                                <thead>
                                    <tr>
                                        @foreach ($isi['columns'] as $column)
                                            <th class="text-{{ $column->align ?: 'left' }}">{{ $column->label }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                @forelse ($isi['rows'] as $row)
                                    <tr>
                                        @foreach ($isi['columns'] as $i => $column)
                                            <td class="text-{{ $column->align ?: 'left' }}">
                                                {{ $row->{'c'.$i} ?? '—' }}
                                            </td>
                                        @endforeach
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ max(1, $isi['columns']->count()) }}"
                                            class="text-center text-muted py-3">Tidak ada data.</td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                @else
                    <div class="card card-outline card-{{ $widget->color }}">
                        <div class="card-header">
                            <h3 class="card-title">
                                @if ($widget->icon)<i class="{{ $widget->icon }} mr-1"></i>@endif
                                {{ $widget->title }}
                            </h3>
                        </div>
                        <div class="card-body">
                            {{-- Isi widget teks dari admin; tetap di-escape. --}}
                            <div style="white-space: pre-line">{{ $widget->content }}</div>
                        </div>
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    @can('system.dashboard')
        <p class="text-muted small">
            <i class="fas fa-cog mr-1"></i>
            Susunan ini diatur di <a href="{{ route('builder.dashboard.index') }}">Atur Dashboard</a>.
        </p>
    @endcan
@endif
@endsection

@push('scripts')
@if ($widgets->contains('type', 'chart'))
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
$(function () {
    const WARNA = ['#0d6efd','#198754','#dc3545','#fd7e14','#6f42c1',
                   '#20c997','#d63384','#0dcaf0','#ffc107','#6610f2'];

    $('canvas[data-chart]').each(function () {
        const payload = $(this).data('chart');
        const jenis = $(this).data('type');
        if (!payload || !payload.datasets) return;

        const lingkaran = jenis === 'pie' || jenis === 'doughnut';

        new Chart(this, {
            type: lingkaran ? jenis : (jenis === 'area' ? 'line' : (jenis === 'horizontal_bar' ? 'bar' : jenis)),
            data: {
                labels: payload.labels,
                datasets: payload.datasets.map((d, i) => lingkaran
                    ? { label: d.label, data: d.data,
                        backgroundColor: payload.labels.map((_, j) => WARNA[j % WARNA.length]) }
                    : { label: d.label, data: d.data,
                        backgroundColor: jenis === 'area' ? d.color + '33' : d.color,
                        borderColor: d.color, borderWidth: 2,
                        fill: jenis === 'area', tension: (jenis === 'line' || jenis === 'area') ? 0.3 : 0 }),
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                indexAxis: jenis === 'horizontal_bar' ? 'y' : 'x',
                plugins: { legend: { display: payload.datasets.length > 1 || lingkaran } },
                scales: lingkaran ? {} : {
                    [jenis === 'horizontal_bar' ? 'x' : 'y']: {
                        beginAtZero: true,
                        ticks: { callback: v => v.toLocaleString('id-ID') },
                    },
                },
            },
        });
    });
});
</script>
@endif
@endpush
