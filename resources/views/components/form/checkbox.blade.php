@props(['field', 'name', 'id', 'value' => null, 'options' => null, 'errorKey' => null])

{{-- Hidden mendahului checkbox agar nilai 0 tetap terkirim saat tidak dicentang. --}}
<input type="hidden" name="{{ $name }}" value="0">

<div class="custom-control custom-checkbox">
    <input type="checkbox"
           class="custom-control-input @error($errorKey) is-invalid @enderror"
           name="{{ $name }}"
           id="{{ $id }}"
           value="1"
           @checked(filter_var($value, FILTER_VALIDATE_BOOLEAN))
           @if ($field->is_readonly) disabled @endif>
    <label class="custom-control-label" for="{{ $id }}">
        {{ $field->label }}
        @if ($field->is_required)<span class="text-danger">*</span>@endif
    </label>
</div>
