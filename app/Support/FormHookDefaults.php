<?php

namespace App\Support;

use App\Models\Form;
use App\Models\User;

/**
 * Implementasi kosong untuk seluruh method FormHook.
 *
 * Sebagian besar hook hanya peduli pada satu titik. Tanpa ini setiap hook harus
 * menulis tiga method kosong, dan method kosong yang banyak membuat yang
 * benar-benar berisi jadi sulit terlihat.
 */
trait FormHookDefaults
{
    public function beforeSave(Form $form, array $values, ?array $before, User $user): array
    {
        return $values;
    }

    public function afterSave(Form $form, mixed $id, array $values, ?array $before, User $user): void {}

    public function beforeDelete(Form $form, mixed $id, array $before, User $user): void {}

    public function afterDelete(Form $form, mixed $id, array $before, User $user): void {}
}
