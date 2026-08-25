@props(['filter', 'values' => [], 'options' => null])

@php
    $name = "f[{$filter->id}]";
    $id = 'rf_'.$filter->id;
    $multi = $filter->isMultiValue() || $filter->input_type === 'multi_select';
    $v0 = $values[0] ?? '';
    $v1 = $values[1] ?? '';
    $width = max(2, min(12, (int) ($filter->width ?: 3)));
@endphp

{{-- Operator is_null / is_not_null tidak butuh masukan apa pun. --}}
@if ($filter->valueCount() === 0)
    @php return; @endphp
@endif

<div class="col-md-{{ $width }}">
    <div class="form-group">
        <label for="{{ $id }}">
            {{ $filter->label }}
            @if ($filter->is_required)<span class="text-danger">*</span>@endif
        </label>

        @switch(true)
            @case($filter->operator === 'between')
                <div class="input-group">
                    <input type="{{ $filter->input_type === 'datetime' ? 'datetime-local' : ($filter->input_type === 'number' ? 'number' : 'date') }}"
                           name="{{ $name }}[]" id="{{ $id }}" class="form-control" value="{{ $v0 }}">
                    <div class="input-group-prepend input-group-append">
                        <span class="input-group-text">s.d.</span>
                    </div>
                    <input type="{{ $filter->input_type === 'datetime' ? 'datetime-local' : ($filter->input_type === 'number' ? 'number' : 'date') }}"
                           name="{{ $name }}[]" class="form-control" value="{{ $v1 }}">
                </div>
                @break

            @case(in_array($filter->input_type, ['select', 'select2', 'multi_select'], true))
                <select name="{{ $name }}{{ $multi ? '[]' : '' }}" id="{{ $id }}"
                        class="form-control {{ $filter->input_type !== 'select' ? 'js-select2' : '' }}"
                        @if ($multi) multiple @endif>
                    @unless ($multi)
                        <option value="">— semua —</option>
                    @endunless
                    @foreach ($options ?? [] as $option)
                        <option value="{{ $option['value'] }}"
                            @selected(in_array((string) $option['value'], array_map('strval', $values), true))>
                            {{ $option['label'] }}
                        </option>
                    @endforeach
                </select>
                @break

            @case($filter->input_type === 'checkbox')
                <div class="custom-control custom-checkbox mt-2">
                    <input type="hidden" name="{{ $name }}" value="">
                    <input type="checkbox" class="custom-control-input" name="{{ $name }}"
                           id="{{ $id }}" value="1" @checked($v0 === '1' || $v0 === 1)>
                    <label class="custom-control-label" for="{{ $id }}">Ya</label>
                </div>
                @break

            @default
                <input type="{{ match ($filter->input_type) {
                        'number' => 'number', 'date' => 'date',
                        'datetime' => 'datetime-local', default => 'text' } }}"
                       name="{{ $name }}" id="{{ $id }}" class="form-control" value="{{ $v0 }}">
        @endswitch
    </div>
</div>
