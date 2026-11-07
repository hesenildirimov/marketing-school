<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Grade extends Model
{
    protected $table = 'grades';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected $casts = ['created_at' => 'datetime', 'superseded_at' => 'datetime'];
}
