{{-- Satu isian pengaturan, digambar dari metadata barisnya. --}}
@php
    $key = $setting->key_name;
    $input = $setting->resolvedInput();
    $value = old("values.{$key}", $setting->value);
    $pesan = $errors->first("values.{$key}") ?: $errors->first("files.{$key}");
@endphp

<div class="{{ $setting->columnClass() }}">
    <div class="form-group">
        @if ($input === 'switch')
            <div class="custom-control custom-switch">
                <input type="hidden" name="values[{{ $key }}]" value="0">
                <input type="checkbox" class="custom-control-input" id="set-{{ $key }}"
                       name="values[{{ $key }}]" value="1"
                       @checked(filter_var($value, FILTER_VALIDATE_BOOLEAN))>
                <label class="custom-control-label" for="set-{{ $key }}">{{ $setting->label ?: $key }}</label>
            </div>

        @else
            <label for="set-{{ $key }}">
                {{ $setting->label ?: $key }}
                <code class="ml-1 small text-muted">{{ $key }}</code>
            </label>

            @if ($input === 'image')
                @php $url = $settings->fileUrl($setting->value); @endphp
                <div class="d-flex align-items-center">
                    <div class="border rounded bg-light mr-3 d-flex align-items-center justify-content-center"
                         style="width: 96px; height: 96px; flex: none">
                        @if ($url)
                            <img src="{{ $url }}" alt="{{ $setting->label }}" style="max-width: 88px; max-height: 88px">
                        @else
                            <i class="fas fa-image fa-2x text-muted"></i>
                        @endif
                    </div>
                    <div class="flex-fill">
                        <input type="file" id="set-{{ $key }}" name="files[{{ $key }}]"
                               class="form-control-file @if ($pesan) is-invalid @endif"
                               accept="image/png,image/jpeg,image/gif,image/webp">
                        @if ($url)
                            <div class="custom-control custom-checkbox mt-2">
                                <input type="checkbox" class="custom-control-input" id="rm-{{ $key }}"
                                       name="remove[{{ $key }}]" value="1">
                                <label class="custom-control-label small text-danger" for="rm-{{ $key }}">
                                    Hapus berkas ini
                                </label>
                            </div>
                        @endif
                    </div>
                </div>

            @elseif ($input === 'select')
                <select id="set-{{ $key }}" name="values[{{ $key }}]"
                        class="form-control @if ($pesan) is-invalid @endif">
                    <option value="">— tidak diisi —</option>
                    @foreach ($setting->choices() as $opsi => $label)
                        <option value="{{ $opsi }}" @selected((string) $value === (string) $opsi)>{{ $label }}</option>
                    @endforeach
                </select>

            @elseif ($input === 'textarea')
                <textarea id="set-{{ $key }}" name="values[{{ $key }}]" rows="3"
                          class="form-control @if ($pesan) is-invalid @endif">{{ $value }}</textarea>

            @elseif ($input === 'number')
                <input type="number" id="set-{{ $key }}" name="values[{{ $key }}]" value="{{ $value }}"
                       min="1" class="form-control @if ($pesan) is-invalid @endif">

            @else
                <input type="text" id="set-{{ $key }}" name="values[{{ $key }}]" value="{{ $value }}"
                       class="form-control @if ($pesan) is-invalid @endif">
            @endif
        @endif

        @if ($setting->description)
            <small class="form-text text-muted">{{ $setting->description }}</small>
        @endif
        @if ($pesan)
            <span class="text-danger small d-block">{{ $pesan }}</span>
        @endif
    </div>
</div>
