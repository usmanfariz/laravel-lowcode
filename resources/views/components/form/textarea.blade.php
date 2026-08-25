@props(['field', 'name', 'id', 'value' => null, 'options' => null, 'errorKey' => null])

<textarea name="{{ $name }}"
          id="{{ $id }}"
          rows="3"
          class="form-control @error($errorKey) is-invalid @enderror"
          @if ($field->placeholder) placeholder="{{ $field->placeholder }}" @endif
          @if ($field->is_readonly) readonly @endif
          @if ($field->is_required) required @endif>{{ $value }}</textarea>
