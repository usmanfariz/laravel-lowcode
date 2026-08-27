<?php

namespace App\Http\Requests\Builder;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class FormActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $form = $this->route('form');
        $action = $this->route('action');

        return [
            'code' => ['required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('form_actions', 'code')->where('form_id', $form->id)->ignore($action?->id)],
            'label' => ['required', 'string', 'max:150'],
            'icon' => ['nullable', 'string', 'max:100'],
            'position' => ['required', Rule::in(['toolbar', 'row', 'bulk'])],
            'action_type' => ['required', Rule::in(['route', 'url', 'ajax', 'modal', 'handler'])],
            'target_value' => ['required', 'string', 'max:255'],
            'http_method' => ['required', Rule::in(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'])],
            'permission_code' => ['nullable', 'string', 'max:150'],
            'confirm_message' => ['nullable', 'string', 'max:255'],
            'css_class' => ['nullable', 'string', 'max:100'],
            'order_no' => ['required', 'integer', 'min:0', 'max:9999'],
            'condition_column' => ['nullable', 'string', 'max:100'],
            'condition_value' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                // Aksi yang mengubah data tanpa konfirmasi mudah terpicu tak
                // sengaja, terutama pada tombol per baris.
                if ($this->input('http_method') !== 'GET' && ! $this->filled('confirm_message')) {
                    $validator->errors()->add('confirm_message',
                        'Aksi selain GET wajib punya pesan konfirmasi.');
                }

                // Aksi massal bekerja pada banyak baris sekaligus; menjalankannya
                // lewat GET berarti bisa terpicu dari tautan biasa.
                if ($this->input('position') === 'bulk' && $this->input('http_method') === 'GET') {
                    $validator->errors()->add('http_method',
                        'Aksi massal tidak boleh memakai GET.');
                }

                $this->checkHandler($validator);
                $this->checkCondition($validator);

                if ($this->input('action_type') === 'route'
                    && $this->filled('target_value')
                    && ! \Route::has($this->input('target_value'))) {
                    // Peringatan saja, bukan penolakan: route bisa saja dibuat
                    // setelah aksinya didefinisikan.
                    session()->flash('warning',
                        "Route '{$this->input('target_value')}' belum terdaftar. "
                        .'Tombolnya akan mengarah ke # sampai route itu dibuat.');
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'code' => 'kode', 'label' => 'label', 'position' => 'posisi',
            'action_type' => 'jenis aksi', 'target_value' => 'tujuan',
            'http_method' => 'metode HTTP', 'confirm_message' => 'pesan konfirmasi',
            'order_no' => 'urutan',
        ];
    }

    /**
     * Aksi berjenis handler hanya boleh menunjuk kunci yang benar-benar
     * terdaftar di config/lowcode.php, dan selalu lewat POST.
     *
     * Kunci bebas ketik akan membuat tombol yang gagal saat diklik; lebih buruk
     * lagi, ia mengaburkan batas bahwa metadata tidak menentukan kode apa yang
     * boleh dijalankan.
     */
    private function checkHandler(Validator $validator): void
    {
        if ($this->input('action_type') !== 'handler') {
            return;
        }

        $registry = app(\App\Services\Form\LowcodeRegistry::class);

        if (! $registry->hasHandler($this->input('target_value'))) {
            $terdaftar = array_keys($registry->handlers());

            $validator->errors()->add('target_value', $terdaftar === []
                ? 'Belum ada handler yang terdaftar di config/lowcode.php.'
                : 'Handler tidak dikenal. Yang terdaftar: '.implode(', ', $terdaftar).'.');
        }

        if ($this->input('http_method') !== 'POST') {
            $validator->errors()->add('http_method',
                'Aksi handler harus memakai POST — ia mengubah data.');
        }
    }

    /**
     * Kondisi tampil harus menunjuk kolom yang benar-benar terbaca engine.
     * Kolom terblokir tidak pernah ikut dikirim ke klien, jadi tombolnya akan
     * selalu tersembunyi tanpa petunjuk apa pun.
     */
    private function checkCondition(Validator $validator): void
    {
        if (! $this->filled('condition_column')) {
            return;
        }

        $form = $this->route('form');

        $diizinkan = app(\App\Services\DataSourceResolver::class)
            ->allowedColumns($form->table_name);

        if (! in_array($this->input('condition_column'), $diizinkan, true)) {
            $validator->errors()->add('condition_column',
                'Kolom tidak tersedia atau diblokir di sumber data.');
        }

        if (! $this->filled('condition_value')) {
            $validator->errors()->add('condition_value',
                'Isi nilainya, atau kosongkan kolomnya agar tombol selalu tampil.');
        }
    }
}
