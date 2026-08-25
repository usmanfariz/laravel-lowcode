@props(['field', 'name', 'id', 'value' => null, 'options' => null, 'errorKey' => null])

@php
    $multiple = $field->isMultiple();
    $selected = $multiple ? (array) ($value ?? []) : [$value];
    // select2, multi_select, ajax_select, dan autocomplete sama-sama digambar
    // Select2; yang membedakan hanya sumber opsinya.
    $useSelect2 = $field->input_type !== 'select';
@endphp

<select name="{{ $name }}{{ $multiple ? '[]' : '' }}"
        id="{{ $id }}"
        class="form-control @error($errorKey) is-invalid @enderror {{ $useSelect2 ? 'js-select2' : '' }}"
        @if ($multiple) multiple @endif
        @if ($field->is_readonly) disabled @endif
        @if ($field->is_required) required @endif
        @if ($field->depends_on)
            data-depends-on="{{ $field->depends_on }}"
            data-depends-column="{{ $field->depends_column }}"
            data-field-id="{{ $field->id }}"
        @endif
        @if ($field->input_type === 'ajax_select')
            data-ajax-field="{{ $field->id }}"
        @endif>

    @unless ($multiple)
        <option value="">{{ $field->placeholder ?: '— pilih —' }}</option>
    @endunless

    @foreach ($options ?? [] as $option)
        <option value="{{ $option['value'] }}"
            @selected(in_array((string) $option['value'], array_map('strval', $selected), true))>
            {{ $option['label'] }}
        </option>
    @endforeach
</select>
