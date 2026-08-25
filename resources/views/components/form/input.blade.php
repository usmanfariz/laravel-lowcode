@props(['field', 'name', 'id', 'value' => null, 'options' => null, 'errorKey' => null])

@php
    $type = match ($field->input_type) {
        'number', 'decimal', 'currency', 'percentage' => 'number',
        'email' => 'email',
        'password' => 'password',
        'url' => 'url',
        'date' => 'date',
        'datetime' => 'datetime-local',
        'time' => 'time',
        'hidden' => 'hidden',
        default => 'text',
    };
    // Uang dan persentase butuh dua desimal; number bulat tidak.
    $step = match ($field->input_type) {
        'decimal', 'currency' => '0.01',
        'percentage' => '0.01',
        default => null,
    };
@endphp

<input type="{{ $type }}"
       name="{{ $name }}"
       id="{{ $id }}"
       class="form-control @error($errorKey) is-invalid @enderror"
       value="{{ $field->input_type === 'password' ? '' : $value }}"
       @if ($step) step="{{ $step }}" @endif
       @if ($field->placeholder) placeholder="{{ $field->placeholder }}" @endif
       @if ($field->is_readonly) readonly @endif
       @if ($field->is_required && $field->input_type !== 'hidden') required @endif>
