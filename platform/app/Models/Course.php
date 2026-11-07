<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['name' => 'array'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function title(): string
    {
        return tr_field($this->name);
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(CourseVersion::class, 'current_version_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(CourseVersion::class);
    }

    public function modules(): HasMany
    {
        return $this->hasMany(Module::class);
    }

    public function instructors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'course_instructors');
    }

    public function draft(): ?CourseVersion
    {
        return $this->versions()->where('status', 'draft')->first();
    }
}
