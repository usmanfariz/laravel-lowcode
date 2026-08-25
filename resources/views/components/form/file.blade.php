@props(['field', 'name', 'id', 'value' => null, 'options' => null, 'errorKey' => null])

@if ($value)
    <div class="mb-2">
        @if ($field->input_type === 'image')
            <img src="{{ Storage::url($value) }}" alt="{{ $field->label }}"
                 class="img-thumbnail" style="max-height:120px">
        @else
            <a href="{{ Storage::url($value) }}" target="_blank" rel="noopener">
                <i class="fas fa-paperclip mr-1"></i>{{ basename($value) }}
            </a>
        @endif
        {{-- Nilai lama dibawa serta agar tidak hilang saat berkas tidak diganti. --}}
        <input type="hidden" name="{{ $name }}_existing" value="{{ $value }}">
    </div>
@endif

<div class="custom-file">
    <input type="file"
           class="custom-file-input @error($errorKey) is-invalid @enderror"
           name="{{ $name }}"
           id="{{ $id }}"
           @if ($field->input_type === 'image') accept="image/*" @endif
           @if ($field->allowed_extensions)
               data-extensions="{{ $field->allowed_extensions }}"
           @endif
           @if ($field->is_readonly) disabled @endif
           @if ($field->is_required && ! $value) required @endif>
    <label class="custom-file-label" for="{{ $id }}">Pilih berkas…</label>
</div>

@if ($field->max_file_size)
    <small class="form-text text-muted">Maksimal {{ number_format($field->max_file_size / 1024, 1) }} MB.</small>
@endif
