<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'form_id', 'code', 'label', 'icon', 'position', 'action_type',
    'target_value', 'http_method', 'permission_code', 'confirm_message',
    'show_condition', 'css_class', 'order_no', 'is_active',
])]
class FormAction extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['show_condition' => 'array', 'is_active' => 'boolean'];
    }
}
