<?php

namespace App\Services\Form;

use App\Models\Form;
use App\Models\FormAction;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Menyiapkan definisi tombol aksi untuk dikirim ke halaman list.
 *
 * Kondisi tampil (show_condition) dievaluasi di klien per baris, jadi kelas
 * ini hanya menyaring yang boleh dilihat user dan menormalkan bentuknya.
 */
class FormActionRenderer
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function forPosition(Form $form, string $position, User $user): array
    {
        return $form->actions
            ->where('position', $position)
            ->filter(fn (FormAction $a) => $this->visibleTo($a, $user))
            ->map(fn (FormAction $a) => $this->normalize($form, $a))
            ->values()
            ->all();
    }

    /** Kolom baris yang dibutuhkan show_condition, supaya endpoint data ikut mengirimnya. */
    public function conditionColumns(Form $form): array
    {
        $columns = [];

        foreach ($form->actions as $action) {
            foreach (array_keys($action->show_condition ?? []) as $column) {
                $columns[] = $column;
            }
        }

        return array_values(array_unique($columns));
    }

    private function visibleTo(FormAction $action, User $user): bool
    {
        return $action->permission_code === null
            || $action->permission_code === ''
            || $user->hasPermission($action->permission_code);
    }

    /** @return array<string, mixed> */
    private function normalize(Form $form, FormAction $action): array
    {
        return [
            'code' => $action->code,
            'label' => $action->label,
            'icon' => $action->icon ?: null,
            'type' => $action->action_type,
            'method' => $action->http_method,
            'url' => $this->url($form, $action),
            'confirm' => $action->confirm_message ?: null,
            'class' => $action->css_class ?: 'btn-default',
            // Kondisi dikirim apa adanya; klien mencocokkannya dengan nilai baris.
            'condition' => $action->show_condition ?: null,
        ];
    }

    /**
     * URL tujuan dengan penanda __ID__ yang diganti klien per baris.
     *
     * Route yang belum terdaftar dikembalikan '#' — satu aksi salah ketik
     * tidak boleh mematikan seluruh halaman list.
     */
    private function url(Form $form, FormAction $action): string
    {
        $target = (string) $action->target_value;

        return match ($action->action_type) {
            'route' => \Route::has($target)
                ? $this->routeUrl($target)
                : '#',
            'modal' => '#'.ltrim($target, '#'),
            default => $target,
        };
    }

    private function routeUrl(string $name): string
    {
        try {
            // Route berparameter diberi penanda; yang tanpa parameter dipakai apa adanya.
            return route($name, ['__ID__']);
        } catch (\Throwable) {
            return route($name);
        }
    }
}
