<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class User extends Authenticatable
{
    protected $guarded = ['id'];

    protected $hidden = ['password'];

    private ?array $roleCache = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'consent_at' => 'datetime',
            'deleted_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // "Remember me" istifadə olunmur: sessiyalar serverdə idarə olunur və ləğv edilir.
    public function getRememberTokenName()
    {
        return '';
    }

    public static function newShortCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $code = '';
            for ($i = 0; $i < 8; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (static::where('short_code', $code)->exists());

        return $code;
    }

    public function roles(): array
    {
        return $this->roleCache ??= DB::table('user_roles')->where('user_id', $this->id)->pluck('role')->all();
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles(), true);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function isInstructor(): bool
    {
        return $this->hasRole('instructor');
    }

    public function isStaff(): bool
    {
        return $this->isAdmin() || $this->isInstructor();
    }

    public function grantRole(string $role): void
    {
        DB::table('user_roles')->insertOrIgnore(['user_id' => $this->id, 'role' => $role]);
        $this->roleCache = null;
    }

    public function revokeRole(string $role): void
    {
        DB::table('user_roles')->where(['user_id' => $this->id, 'role' => $role])->delete();
        $this->roleCache = null;
    }

    /** @return int[] Təlimçinin kursları */
    public function taughtCourseIds(): array
    {
        if (! $this->isInstructor() || ! Schema::hasTable('course_instructors')) {
            return [];
        }

        return DB::table('course_instructors')->where('user_id', $this->id)->pluck('course_id')->map(fn ($v) => (int) $v)->all();
    }

    public function twoFactor(): HasOne
    {
        return $this->hasOne(TwoFactorSecret::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(UserSession::class);
    }
}
