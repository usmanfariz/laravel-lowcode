@props(['field', 'name', 'id', 'value' => null, 'options' => null, 'errorKey' => null])

{{--
    Editor kaya belum dipasang pustaka WYSIWYG-nya. Sementara ini digambar
    sebagai textarea agar data tetap tersimpan dan tidak ada field yang hilang.
--}}
<textarea name="{{ $name }}"
          id="{{ $id }}"
          rows="8"
          class="form-control js-editor @error($errorKey) is-invalid @enderror"
          @if ($field->is_readonly) readonly @endif
          @if ($field->is_required) required @endif>{{ $value }}</textarea>
