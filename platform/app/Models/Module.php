<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['title' => 'array', 'description' => 'array'];

    public function label(): string
    {
        return tr_field($this->title);
    }
}
