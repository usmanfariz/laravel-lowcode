@props(['field', 'value' => null, 'options' => null, 'prefix' => ''])

@php
    $name = $prefix ? "{$prefix}[{$field->field_name}]" : $field->field_name;
    $errorKey = $prefix
        ? str_replace(['[', ']'], ['.', ''], "{$prefix}[{$field->field_name}]")
        : $field->field_name;
    $id = 'f_'.str_replace(['[', ']', '.'], '_', $name);
@endphp

<div class="{{ $field->columnClass() }} {{ $field->input_type === 'hidden' ? 'd-none' : '' }}">
    <div class="form-group">
        @unless (in_array($field->input_type, ['hidden', 'switch', 'checkbox'], true))
            <label for="{{ $id }}">
                {{ $field->label }}
                @if ($field->is_required)<span class="text-danger">*</span>@endif
            </label>
        @endunless

        <x-dynamic-component
            :component="$field->component()"
            :field="$field"
            :name="$name"
            :id="$id"
            :value="$value"
            :options="$options"
            :error-key="$errorKey" />

        @if ($field->help_text)
            <small class="form-text text-muted">{{ $field->help_text }}</small>
        @endif

        @error($errorKey)
            <span class="invalid-feedback d-block">{{ $message }}</span>
        @enderror
    </div>
</div>
