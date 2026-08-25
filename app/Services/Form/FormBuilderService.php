<?php

namespace App\Services\Form;

use App\Models\Form;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Menyimpan riwayat definisi form dan membersihkan cache metadata.
 *
 * Setiap perubahan lewat builder wajib melewati kelas ini: tanpa flush,
 * halaman form tetap menampilkan definisi lama sampai cache kedaluwarsa.
 */
class FormBuilderService
{
    public function __construct(private readonly FormService $forms) {}

    /**
     * Bersihkan seluruh cache yang menyangkut satu form.
     *
     * Opsi select di-cache per id field, jadi ikut dibuang — kalau tidak,
     * mengganti sumber data field tidak terlihat sampai 10 menit berikutnya.
     */
    public function flush(Form $form): void
    {
        $this->forms->flush($form->code);

        foreach (DB::table('form_fields')->where('form_id', $form->id)->pluck('id') as $fieldId) {
            Cache::forget('form.options.'.$fieldId);
        }

        foreach (DB::table('form_fields')->where('form_id', $form->id)
            ->where('data_source_type', 'enum')->get(['data_source', 'value_field']) as $field) {
            Cache::forget("form.enum.{$field->data_source}.{$field->value_field}");
        }
    }

    /**
     * Rekam snapshot definisi form saat ini ke form_versions.
     *
     * Dipanggil SEBELUM perubahan disimpan, sehingga versi terekam adalah
     * keadaan yang bisa dikembalikan bila perubahan berikutnya keliru.
     */
    public function snapshot(Form $form, User $user, ?string $note = null): int
    {
        $version = (int) DB::table('form_versions')->where('form_id', $form->id)->max('version') + 1;

        DB::table('form_versions')->insert([
            'form_id' => $form->id,
            'version' => $version,
            'snapshot' => json_encode($this->definition($form), JSON_UNESCAPED_UNICODE),
            'note' => $note,
            'created_by' => $user->id,
            'created_at' => now(),
        ]);

        return $version;
    }

    /**
     * Seluruh definisi form sebagai larik biasa — dipakai snapshot maupun
     * ekspor definisi.
     */
    public function definition(Form $form): array
    {
        return [
            'form' => DB::table('forms')->where('id', $form->id)->first(),
            'fields' => DB::table('form_fields')->where('form_id', $form->id)
                ->orderBy('order_no')->get(),
            'field_options' => DB::table('form_field_options')
                ->whereIn('form_field_id', DB::table('form_fields')->where('form_id', $form->id)->select('id'))
                ->orderBy('order_no')->get(),
            'list_columns' => DB::table('form_list_columns')->where('form_id', $form->id)
                ->orderBy('order_no')->get(),
            'details' => DB::table('form_details')->where('form_id', $form->id)
                ->orderBy('order_no')->get(),
            'actions' => DB::table('form_actions')->where('form_id', $form->id)
                ->orderBy('order_no')->get(),
        ];
    }

    /**
     * Kembalikan form ke salah satu versi tersimpan.
     *
     * Keadaan sekarang di-snapshot lebih dulu supaya pemulihan sendiri pun
     * bisa dibatalkan.
     */
    public function restore(Form $form, int $version, User $user): void
    {
        $row = DB::table('form_versions')
            ->where('form_id', $form->id)
            ->where('version', $version)
            ->first();

        abort_if($row === null, 404, "Versi {$version} tidak ditemukan.");

        $snapshot = json_decode($row->snapshot, true);

        DB::transaction(function () use ($form, $snapshot, $user, $version) {
            $this->snapshot($form, $user, "Sebelum dikembalikan ke versi {$version}");

            $fieldIds = DB::table('form_fields')->where('form_id', $form->id)->pluck('id');
            DB::table('form_field_options')->whereIn('form_field_id', $fieldIds)->delete();
            DB::table('form_fields')->where('form_id', $form->id)->delete();
            DB::table('form_list_columns')->where('form_id', $form->id)->delete();

            DB::table('forms')->where('id', $form->id)
                ->update(collect((array) $snapshot['form'])->except('id')->all());

            foreach ($snapshot['fields'] as $field) {
                $old = $field['id'];
                $new = DB::table('form_fields')->insertGetId(
                    collect($field)->except('id')->all()
                );

                foreach ($snapshot['field_options'] as $option) {
                    if ($option['form_field_id'] === $old) {
                        DB::table('form_field_options')->insert(
                            collect($option)->except('id')->merge(['form_field_id' => $new])->all()
                        );
                    }
                }
            }

            foreach ($snapshot['list_columns'] as $column) {
                DB::table('form_list_columns')->insert(collect($column)->except('id')->all());
            }
        });

        $this->flush($form);
    }
}
