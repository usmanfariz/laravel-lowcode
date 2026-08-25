@php
    $tabs = [
        'builder.forms.edit' => ['Pengaturan', 'fas fa-cog'],
        'builder.fields.index' => ['Field ('.$form->allFields()->count().')', 'fas fa-list'],
        'builder.fields.layout' => ['Tata Letak', 'fas fa-th-large'],
        'builder.columns.index' => ['Kolom List ('.$form->listColumns->count().')', 'fas fa-columns'],
        'builder.details.index' => ['Detail ('.$form->allDetails()->count().')', 'fas fa-layer-group'],
        'builder.actions.index' => ['Aksi ('.$form->allActions()->count().')', 'fas fa-hand-pointer'],
    ];
@endphp

<div class="card card-outline card-primary">
    <div class="card-header p-0 border-bottom-0">
        <ul class="nav nav-tabs">
            @foreach ($tabs as $route => [$label, $icon])
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs($route) ? 'active' : '' }}"
                       href="{{ route($route, $form) }}">
                        <i class="{{ $icon }} mr-1"></i>{{ $label }}
                    </a>
                </li>
            @endforeach
            <li class="nav-item ml-auto">
                <a class="nav-link text-muted" href="{{ url('forms/'.$form->code) }}" target="_blank">
                    <i class="fas fa-external-link-alt mr-1"></i>Buka Form
                </a>
            </li>
        </ul>
    </div>
</div>
