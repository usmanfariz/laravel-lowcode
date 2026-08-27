<?php

namespace Tests\Feature;

use App\Models\DataSource;
use App\Models\Form;
use App\Services\Form\FormRepository;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\MetadataTestCase;

class ComputedFieldTest extends MetadataTestCase
{
    private Form $form;

    protected function setUp(): void
    {
        parent::setUp();

        // Tabel detail sendiri, supaya fixture bersama tidak perlu diubah.
        Schema::create('t_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('item_id');
            $table->integer('qty')->default(0);
            $table->decimal('harga', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2)->default(0);
        });

        DataSource::create([
            'connection' => config('database.default'), 'table_name' => 't_lines',
            'label' => 'Baris', 'primary_key' => 'id', 'is_readable' => true,
            'is_writable' => true, 'blocked_columns' => [], 'is_active' => true,
        ]);

        $this->form = $this->makeForm();
    }

    private function jadikanTerhitung(string $field, string $rumus): void
    {
        DB::table('form_fields')
            ->where('form_id', $this->form->id)
            ->whereNull('form_detail_id')
            ->where('field_name', $field)
            ->update(['formula' => $rumus, 'is_readonly' => true]);

        $this->form->refresh()->load('fields');
    }

    private function buatDetail(array $fields): int
    {
        $detailId = DB::table('form_details')->insertGetId([
            'form_id' => $this->form->id, 'code' => 'lines', 'title' => 'Baris',
            'table_name' => 't_lines', 'primary_key' => 'id', 'foreign_key' => 'item_id',
            'min_rows' => 0, 'max_rows' => null, 'allow_add' => true, 'allow_delete' => true,
            'show_total_row' => false, 'order_no' => 1, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $urut = 0;
        foreach ($fields as [$nama, $tipe, $rumus]) {
            DB::table('form_fields')->insert([
                'form_id' => $this->form->id, 'form_detail_id' => $detailId,
                'field_name' => $nama, 'label' => ucfirst($nama), 'input_type' => $tipe,
                'is_required' => false, 'is_readonly' => (bool) $rumus, 'is_unique' => false,
                'show_total' => false, 'formula' => $rumus,
                'width' => 4, 'order_no' => ++$urut,
                'data_source_type' => 'none', 'is_active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->form->refresh()->load(['fields', 'details.fields']);

        return $detailId;
    }

    private function simpan(array $input): mixed
    {
        return app(FormRepository::class)->create($this->form, array_merge([
            'code' => 'A1', 'name' => 'Item', 'price' => 0, 'qty' => 0,
        ], $input), $this->admin);
    }

    // ---------------- Inti keamanannya ----------------

    #[Test]
    public function nilai_kiriman_untuk_field_terhitung_diabaikan(): void
    {
        // Ini alasan perhitungan ulang di server ada sama sekali: tanpa ini,
        // siapa pun bisa mem-POST harga sesukanya lewat devtools.
        $this->jadikanTerhitung('price', 'qty * 1000');

        $id = $this->simpan(['qty' => 3, 'price' => 1]);

        $this->assertEquals(3000, DB::table('t_items')->find($id)->price);
    }

    #[Test]
    public function field_terhitung_diperbarui_saat_edit_juga(): void
    {
        $this->jadikanTerhitung('price', 'qty * 1000');
        $id = $this->simpan(['qty' => 3]);

        app(FormRepository::class)->update($this->form, $id, [
            'code' => 'A1', 'name' => 'Item', 'qty' => 5, 'price' => 999999,
        ], $this->admin);

        $this->assertEquals(5000, DB::table('t_items')->find($id)->price);
    }

    // ---------------- Baris detail ----------------

    #[Test]
    public function rumus_dihitung_per_baris_detail(): void
    {
        $this->buatDetail([
            ['qty', 'number', null],
            ['harga', 'currency', null],
            ['subtotal', 'currency', 'qty * harga'],
        ]);

        $id = $this->simpan(['detail' => ['lines' => [
            ['qty' => 2, 'harga' => 1500, 'subtotal' => 0],
            ['qty' => 3, 'harga' => 1000, 'subtotal' => 999],
        ]]]);

        $baris = DB::table('t_lines')->where('item_id', $id)->orderBy('id')->get();

        // 2 x 1500 dan 3 x 1000 sama-sama 3000; nilai kiriman (0 dan 999) diabaikan.
        $this->assertEquals(3000, $baris[0]->subtotal);
        $this->assertEquals(3000, $baris[1]->subtotal);
    }

    #[Test]
    public function rumus_induk_menjumlahkan_hasil_rumus_detail(): void
    {
        // Rantai lengkapnya: subtotal detail dihitung, lalu dijumlahkan, lalu
        // dipakai rumus induk. Menjumlahkan nilai kiriman akan salah di sini.
        $this->buatDetail([
            ['qty', 'number', null],
            ['harga', 'currency', null],
            ['subtotal', 'currency', 'qty * harga'],
        ]);
        $this->jadikanTerhitung('price', 'sum(lines.subtotal)');

        $id = $this->simpan(['price' => 7, 'detail' => ['lines' => [
            ['qty' => 2, 'harga' => 1500, 'subtotal' => 0],
            ['qty' => 3, 'harga' => 1000, 'subtotal' => 0],
        ]]]);

        $this->assertEquals(6000, DB::table('t_items')->find($id)->price);
    }

    #[Test]
    public function detail_kosong_membuat_jumlahnya_nol(): void
    {
        $this->buatDetail([['qty', 'number', null], ['harga', 'currency', null], ['subtotal', 'currency', 'qty * harga']]);
        $this->jadikanTerhitung('price', 'sum(lines.subtotal)');

        $id = $this->simpan(['price' => 500, 'detail' => ['lines' => []]]);

        $this->assertEquals(0, DB::table('t_items')->find($id)->price);
    }

    // ---------------- Pembulatan ----------------

    #[Test]
    public function pembulatan_sama_dengan_sisi_klien(): void
    {
        // 'number' dulu memakai (int) yang memotong: 2,7 jadi 2, sementara
        // klien membulatkan jadi 3.
        $this->jadikanTerhitung('qty', 'price / 2');

        $id = $this->simpan(['price' => 5]);

        $this->assertSame(3, (int) DB::table('t_items')->find($id)->qty);
    }

    // ---------------- Validasi builder ----------------

    private function kirimField(array $data)
    {
        return $this->actingAs($this->admin)->post(
            "/builder/forms/{$this->form->id}/fields",
            array_merge([
                'field_name' => 'price', 'label' => 'Harga', 'input_type' => 'currency',
                'width' => 6, 'order_no' => 50, 'data_source_type' => 'none', 'is_active' => 1,
            ], $data)
        );
    }

    #[Test]
    public function rumus_pada_field_bukan_angka_ditolak(): void
    {
        $this->kirimField(['field_name' => 'name', 'input_type' => 'text', 'formula' => 'qty * 2'])
            ->assertSessionHasErrors('formula');
    }

    #[Test]
    public function rumus_dengan_sintaks_salah_ditolak(): void
    {
        $this->kirimField(['field_name' => 'branch_code', 'input_type' => 'number', 'formula' => 'qty * ('])
            ->assertSessionHasErrors('formula');
    }

    #[Test]
    public function rumus_yang_menunjuk_field_tak_ada_ditolak(): void
    {
        $this->kirimField(['field_name' => 'branch_code', 'input_type' => 'number', 'formula' => 'tidak_ada * 2'])
            ->assertSessionHasErrors('formula');
    }

    #[Test]
    public function rumus_yang_merujuk_dirinya_sendiri_ditolak(): void
    {
        $this->kirimField(['field_name' => 'branch_code', 'input_type' => 'number', 'formula' => 'branch_code + 1'])
            ->assertSessionHasErrors('formula');
    }

    #[Test]
    public function sum_pada_field_detail_ditolak(): void
    {
        $detailId = $this->buatDetail([['qty', 'number', null]]);

        $this->actingAs($this->admin)->post("/builder/forms/{$this->form->id}/fields", [
            'field_name' => 'subtotal', 'label' => 'Subtotal', 'input_type' => 'currency',
            'form_detail_id' => $detailId, 'width' => 6, 'order_no' => 9,
            'data_source_type' => 'none', 'is_active' => 1,
            'formula' => 'sum(lines.qty)',
        ])->assertSessionHasErrors('formula');
    }

    #[Test]
    public function sum_ke_detail_yang_tak_ada_ditolak(): void
    {
        $this->kirimField(['field_name' => 'branch_code', 'input_type' => 'number', 'formula' => 'sum(hantu.qty)'])
            ->assertSessionHasErrors('formula');
    }

    #[Test]
    public function rumus_yang_sah_diterima_dan_field_jadi_hanya_baca(): void
    {
        $this->kirimField([
            'field_name' => 'branch_code', 'input_type' => 'number',
            'formula' => 'qty * 2', 'is_readonly' => 0,
        ])->assertSessionHasNoErrors();

        $field = DB::table('form_fields')->where('field_name', 'branch_code')->first();

        $this->assertSame('qty * 2', $field->formula);
        $this->assertEquals(1, $field->is_readonly, 'field terhitung harus otomatis hanya-baca');
    }
}
