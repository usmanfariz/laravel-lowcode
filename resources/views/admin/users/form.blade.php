@extends('layouts.adminlte.app')
@section('title', $user->exists ? 'Ubah Pengguna' : 'Tambah Pengguna')
@section('page-title', $user->exists ? 'Ubah Pengguna' : 'Tambah Pengguna')

@section('content')
<form method="POST" action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}">
    @csrf
    @if ($user->exists) @method('PUT') @endif

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Data Pengguna</h3></div>
                <div class="card-body">
                    <div class="form-group">
                        <label>Username <span class="text-danger">*</span></label>
                        <input type="text" name="username" class="form-control @error('username') is-invalid @enderror"
                               value="{{ old('username', $user->username) }}" required>
                        @error('username')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>

                    <div class="form-group">
                        <label>Nama <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                               value="{{ old('name', $user->name) }}" required>
                        @error('name')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>

                    <div class="form-group">
                        <label>Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                               value="{{ old('email', $user->email) }}" required>
                        @error('email')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>

                    <div class="form-group">
                        <label>Telepon</label>
                        <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror"
                               value="{{ old('phone', $user->phone) }}">
                        @error('phone')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>

                    <hr>

                    <div class="form-group">
                        <label>Password @if (! $user->exists)<span class="text-danger">*</span>@endif</label>
                        <input type="password" name="password" autocomplete="new-password"
                               class="form-control @error('password') is-invalid @enderror"
                               @if (! $user->exists) required @endif>
                        @error('password')<span class="invalid-feedback">{{ $message }}</span>@enderror
                        @if ($user->exists)
                            <small class="form-text text-muted">Kosongkan bila tidak ingin mengubah password.</small>
                        @endif
                    </div>

                    <div class="form-group">
                        <label>Ulangi Password</label>
                        <input type="password" name="password_confirmation" autocomplete="new-password" class="form-control">
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Akses</h3></div>
                <div class="card-body">
                    <div class="form-group">
                        <label>Role</label>
                        <select name="roles[]" class="form-control select2" multiple>
                            @foreach ($roles as $role)
                                <option value="{{ $role->id }}"
                                    @selected(in_array($role->id, old('roles', $selectedRoles)))>
                                    {{ $role->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('roles')<span class="text-danger small">{{ $message }}</span>@enderror
                    </div>

                    <div class="form-group">
                        <label>Nilai Scope</label>
                        <input type="text" name="scope_value" class="form-control"
                               value="{{ old('scope_value', $user->scope_value) }}">
                        <small class="form-text text-muted">
                            Dibandingkan dengan kolom scope pada form/report untuk role
                            dengan cakupan data <code>branch</code> atau <code>custom</code>.
                        </small>
                    </div>

                    <div class="custom-control custom-switch">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" class="custom-control-input" id="is_active" name="is_active" value="1"
                               @checked(old('is_active', $user->is_active ?? true))>
                        <label class="custom-control-label" for="is_active">Aktif</label>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Simpan</button>
                    <a href="{{ route('users.index') }}" class="btn btn-default">Batal</a>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>$(function () { $('.select2').select2({ width: '100%' }); });</script>
@endpush
