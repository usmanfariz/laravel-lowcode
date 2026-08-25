<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ActivityLogger
{
    /**
     * Catat satu aktivitas ke activity_logs.
     *
     * Kegagalan pencatatan tidak boleh menggagalkan aksi bisnisnya — log
     * bersifat pelengkap, bukan syarat.
     */
    public function record(
        string $event,
        string $table,
        mixed $recordId,
        ?array $oldValues,
        ?array $newValues,
        ?string $module = null,
    ): void {
        try {
            DB::table('activity_logs')->insert([
                'user_id' => auth()->id(),
                'event' => $event,
                'module' => $module,
                'table_name' => $table,
                'record_id' => (string) $recordId,
                'description' => null,
                'old_values' => $this->encode($oldValues),
                'new_values' => $this->encode($newValues),
                'ip_address' => request()->ip(),
                'user_agent' => substr((string) request()->userAgent(), 0, 255),
                'url' => substr((string) request()->fullUrl(), 0, 255),
                'http_method' => request()->method(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function encode(?array $values): ?string
    {
        if ($values === null) {
            return null;
        }

        // Nilai berupa objek (mis. Carbon dari kolom timestamp) tidak
        // dijadikan JSON apa adanya agar payload log tetap datar.
        $flat = array_map(
            fn ($v) => is_scalar($v) || $v === null ? $v : (string) $v,
            $values
        );

        return json_encode($flat, JSON_UNESCAPED_UNICODE);
    }
}
