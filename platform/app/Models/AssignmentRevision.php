<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssignmentRevision extends Model
{
    protected $table = 'assignment_revisions';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected $casts = ['title' => 'array', 'body' => 'array', 'due_at' => 'datetime', 'created_at' => 'datetime'];
}
