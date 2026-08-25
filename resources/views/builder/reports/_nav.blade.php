@php
    $tabs = [
        'builder.reports.edit' => ['Pengaturan', 'fas fa-cog'],
        'builder.reports.joins.index' => ['Join ('.$report->joins->count().')', 'fas fa-link'],
        'builder.reports.columns.index' => ['Kolom ('.$report->columns->count().')', 'fas fa-columns'],
        'builder.reports.filters.index' => ['Filter ('.$report->filters->count().')', 'fas fa-filter'],
    ];
@endphp

<div class="card card-outline card-primary">
    <div class="card-header p-0 border-bottom-0">
        <ul class="nav nav-tabs">
            @foreach ($tabs as $route => [$label, $icon])
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs($route) ? 'active' : '' }}"
                       href="{{ route($route, $report) }}">
                        <i class="{{ $icon }} mr-1"></i>{{ $label }}
                    </a>
                </li>
            @endforeach
            <li class="nav-item ml-auto">
                <a class="nav-link text-muted" href="{{ url('reports/'.$report->code) }}" target="_blank">
                    <i class="fas fa-external-link-alt mr-1"></i>Buka Report
                </a>
            </li>
        </ul>
    </div>
</div>
