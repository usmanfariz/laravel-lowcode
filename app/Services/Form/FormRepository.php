<?php

namespace App\Services\Form;

use App\Models\Form;
use App\Models\FormField;
use App\Models\User;
use App\Exceptions\StaleRecordException;
use App\Services\ActivityLogger;
use App\Services\DataSourceResolver;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Menulis data bisnis lewat definisi form.
 *
 * Setiap nama kolom yang dipakai berasal dari form_fields dan tetap diperiksa
 * ke DataSourceResolver — metadata bisa disunting lewat builder, jadi tidak
 * boleh dipercaya begitu saja.
 */
class FormRepository
{
    public function __construct(
        private readonly DataSourceResolver $sources,
        private readonly FileHandler $files,
        private readonly ActivityLogger $log,
    ) {}

    /** @return mixed primary key baris baru */
    public function create(Form $form, array $input, User $user): mixed
    {
        $this->sources->assertWritable($form->table_name);

        return DB::transaction(function () use ($form, $input, $user) {
            $values = $this->mapValues($form, $input, $user, null);

            if ($form->use_audit_column) {
                $values = $this->withAudit($form->table_name, $values, $user, true);
            }
            $values = $this->withTimestamps($form->table_name, $values, true);

            // Baris baru selalu memakai scope milik pembuatnya, bukan nilai
            // dari request — kalau tidak, user bisa menitipkan data ke unit lain.
            if ($form->scope_column && $user->dataScope() !== 'all') {
                $values[$this->sources->assertColumn($form->table_name, $form->scope_column)]
                    = $user->scope_value;
            }

            $id = $this->sources->query($form->table_name)->insertGetId($values);

            $this->saveDetails($form, $input, $id, $user);
            $this->log->record('create', $form->table_name, $id, null, $values, $form->code);

            return $id;
        });
    }

    /**
     * @param  string|null  $expectedVersion  nilai updated_at saat form dibuka,
     *                                        untuk mendeteksi perubahan bersamaan
     */
    public function update(Form $form, mixed $id, array $input, User $user, ?string $expectedVersion = null): void
    {
        $this->sources->assertWritable($form->table_name);

        DB::transaction(function () use ($form, $id, $input, $user, $expectedVersion) {
            $before = (array) $this->findOrFail($form, $id, $user);

            $this->assertNotStale($form, $before, $expectedVersion);

            $values = $this->mapValues($form, $input, $user, $before);

            if ($form->use_audit_column) {
                $values = $this->withAudit($form->table_name, $values, $user, false);
            }
            $values = $this->withTimestamps($form->table_name, $values, false);

            // scope_column tidak pernah ikut diubah lewat form.
            if ($form->scope_column) {
                unset($values[$form->scope_column]);
            }

            $this->rowQuery($form, $id, $user)->update($values);

            $this->saveDetails($form, $input, $id, $user);
            $this->log->record('update', $form->table_name, $id, $before, $values, $form->code);
        });
    }

    public function delete(Form $form, mixed $id, User $user): void
    {
        $this->sources->assertWritable($form->table_name);

        DB::transaction(function () use ($form, $id, $user) {
            $before = (array) $this->findOrFail($form, $id, $user);

            if ($form->use_soft_delete) {
                // Berkas sengaja dibiarkan: baris yang di-soft-delete masih
                // bisa dikembalikan, dan tanpa berkasnya ia jadi tidak utuh.
                $this->rowQuery($form, $id, $user)->update(['deleted_at' => now()]);
            } else {
                foreach ($form->details as $detail) {
                    $this->sources->assertWritable($detail->table_name);
                    $this->sources->query($detail->table_name)
                        ->where($this->sources->assertColumn($detail->table_name, $detail->foreign_key), $id)
                        ->delete();
                }

                $this->rowQuery($form, $id, $user)->delete();

                // Penghapusan permanen: berkasnya ikut dibuang agar tidak
                // menumpuk sebagai sampah yang tidak ditunjuk baris mana pun.
                $this->deleteFiles($form, $before);
            }

            $this->log->record('delete', $form->table_name, $id, $before, null, $form->code);
        });
    }

    /**
     * Tolak penyimpanan bila baris sudah berubah sejak form dibuka.
     *
     * Tanpa ini, dua orang yang mengedit baris sama membuat yang terakhir
     * menyimpan menimpa pekerjaan yang pertama tanpa peringatan apa pun.
     *
     * Hanya berlaku bila tabelnya punya updated_at dan formnya memang
     * mengirim penanda versi — tabel tanpa timestamp tidak bisa dilindungi.
     */
    private function assertNotStale(Form $form, array $before, ?string $expectedVersion): void
    {
        if ($expectedVersion === null || ! array_key_exists('updated_at', $before)) {
            return;
        }

        $current = $before['updated_at'];

        if ($current === null) {
            return;
        }

        // Dibandingkan sebagai string agar selisih format tidak dianggap beda.
        if ((string) $current !== $expectedVersion) {
            throw new StaleRecordException(
                'Data ini sudah diubah orang lain sejak Anda membukanya. '
                .'Muat ulang halaman dan ulangi perubahan Anda agar pekerjaan '
                .'orang lain tidak tertimpa.'
            );
        }
    }

    /**
     * Buang berkas unggahan milik satu baris.
     *
     * @param  array<string, mixed>  $row  nilai baris sebelum dihapus
     */
    private function deleteFiles(Form $form, array $row): void
    {
        foreach ($form->fields as $field) {
            if (! in_array($field->input_type, ['file', 'image'], true)) {
                continue;
            }

            $this->files->delete($row[$field->field_name] ?? null);
        }
    }

    /** Baris untuk form edit, sudah tersaring scope. */
    public function find(Form $form, mixed $id, User $user): array
    {
        return (array) $this->findOrFail($form, $id, $user);
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    public function detailRows(Form $form, mixed $id): array
    {
        $rows = [];

        foreach ($form->details as $detail) {
            $columns = $this->sources->allowedColumns($detail->table_name);

            $rows[$detail->code] = $this->sources->query($detail->table_name)
                ->select($columns)
                ->where($this->sources->assertColumn($detail->table_name, $detail->foreign_key), $id)
                ->get()
                ->map(fn ($r) => (array) $r)
                ->all();
        }

        return $rows;
    }

    // ------------------------------------------------------------------

    private function findOrFail(Form $form, mixed $id, User $user): object
    {
        $row = $this->rowQuery($form, $id, $user)
            ->select($this->sources->allowedColumns($form->table_name))
            ->first();

        abort_if($row === null, 404, 'Data tidak ditemukan.');

        return $row;
    }

    /** Query satu baris, sudah dibatasi scope agar user tidak menyentuh baris unit lain. */
    private function rowQuery(Form $form, mixed $id, User $user)
    {
        $query = $this->sources->query($form->table_name)
            ->where($this->sources->assertColumn($form->table_name, $form->primary_key), $id);

        if ($form->scope_column && $user->dataScope() !== 'all') {
            $query->where(
                $this->sources->assertColumn($form->table_name, $form->scope_column),
                $user->scope_value ?? '__tanpa_scope__'
            );
        }

        if ($form->use_soft_delete) {
            $query->whereNull('deleted_at');
        }

        return $query;
    }

    /**
     * Ubah input request menjadi pasangan kolom→nilai, hanya untuk field yang
     * benar-benar terdaftar di metadata dan lolos whitelist kolom.
     */
    private function mapValues(Form $form, array $input, User $user, ?array $before): array
    {
        $values = [];

        foreach ($form->fields as $field) {
            if ($field->is_readonly) {
                continue;
            }

            $column = $this->sources->assertColumn($form->table_name, $field->field_name);
            $name = $field->field_name;

            if (in_array($field->input_type, ['file', 'image'], true)) {
                $file = $input[$name] ?? null;

                if ($file instanceof UploadedFile) {
                    // Berkas lama dibuang setelah yang baru tersimpan.
                    $this->files->delete($before[$name] ?? null);
                    $values[$column] = $this->files->store($field, $file);
                } elseif (isset($input[$name.'_existing'])) {
                    $values[$column] = $input[$name.'_existing'];
                }

                continue;
            }

            if (! array_key_exists($name, $input)) {
                continue;
            }

            $values[$column] = $this->castValue($field, $input[$name]);
        }

        return $values;
    }

    private function castValue(FormField $field, mixed $value): mixed
    {
        if ($value === '' ) {
            // String kosong disimpan sebagai NULL agar kolom nullable tidak
            // terisi string kosong yang menyulitkan pencarian.
            return $field->is_required ? '' : null;
        }

        return match ($field->input_type) {
            'switch', 'checkbox' => (int) filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'number' => (int) $value,
            'decimal', 'currency', 'percentage' => (float) $value,
            'multi_select' => json_encode(array_values((array) $value)),
            default => $value,
        };
    }

    private function saveDetails(Form $form, array $input, mixed $parentId, User $user): void
    {
        foreach ($form->details as $detail) {
            if (! array_key_exists($detail->code, $input['detail'] ?? [])) {
                continue;
            }

            $this->sources->assertWritable($detail->table_name);
            $foreignKey = $this->sources->assertColumn($detail->table_name, $detail->foreign_key);

            // Baris detail ditulis ulang seluruhnya: lebih sederhana dan
            // menghindari selisih antara baris yang dihapus di layar dan di DB.
            $this->sources->query($detail->table_name)->where($foreignKey, $parentId)->delete();

            foreach ($input['detail'][$detail->code] as $row) {
                $values = [$foreignKey => $parentId];

                foreach ($detail->fields as $field) {
                    if (! array_key_exists($field->field_name, $row)) {
                        continue;
                    }

                    $column = $this->sources->assertColumn($detail->table_name, $field->field_name);
                    $values[$column] = $this->castValue($field, $row[$field->field_name]);
                }

                $this->sources->query($detail->table_name)->insert($values);
            }
        }
    }

    private function withAudit(string $table, array $values, User $user, bool $isCreate): array
    {
        $columns = $this->sources->allowedColumns($table);

        if ($isCreate && in_array('created_by', $columns, true)) {
            $values['created_by'] = $user->id;
        }
        if (in_array('updated_by', $columns, true)) {
            $values['updated_by'] = $user->id;
        }

        return $values;
    }

    private function withTimestamps(string $table, array $values, bool $isCreate): array
    {
        $columns = $this->sources->allowedColumns($table);

        if ($isCreate && in_array('created_at', $columns, true)) {
            $values['created_at'] = now();
        }
        if (in_array('updated_at', $columns, true)) {
            $values['updated_at'] = now();
        }

        return $values;
    }
}
