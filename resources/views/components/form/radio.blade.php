@props(['field', 'name', 'id', 'value' => null, 'options' => null, 'errorKey' => null])

<div>
    @foreach ($options ?? [] as $i => $option)
        <div class="custom-control custom-radio {{ $field->width >= 6 ? 'custom-control-inline' : '' }}">
            <input type="radio"
                   class="custom-control-input @error($errorKey) is-invalid @enderror"
                   name="{{ $name }}"
                   id="{{ $id }}_{{ $i }}"
                   value="{{ $option['value'] }}"
                   @checked((string) $option['value'] === (string) $value)
                   @if ($field->is_readonly) disabled @endif>
            <label class="custom-control-label" for="{{ $id }}_{{ $i }}">{{ $option['label'] }}</label>
        </div>
    @endforeach
</div>
