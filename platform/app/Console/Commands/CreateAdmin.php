<?php

namespace App\Console\Commands;

use App\Domain\Audit\Audit;
use App\Models\User;
use App\Support\Normalizer;
use App\Support\System;
use Illuminate\Console\Command;

/** İlk admin və ya təlimçi hesabı (serverdə, SSH ilə). Giriş zamanı 2FA quraşdırması məcburidir. */
class CreateAdmin extends Command
{
    protected $signature = 'platform:user:create {email} {name} {--role=admin} {--course=* : təlimçi üçün kurs slug-ları}';

    protected $description = 'Admin/təlimçi hesabı yaradır';

    public function handle(): int
    {
        $role = $this->option('role');
        if (! in_array($role, ['admin', 'instructor'], true)) {
            $this->error('role: admin|instructor');

            return self::FAILURE;
        }
        $password = $this->secret('Parol (min. '.config('platform.auth.password_min_length').' simvol)');
        if (mb_strlen((string) $password) < config('platform.auth.password_min_length')) {
            $this->error('Parol çox qısadır');

            return self::FAILURE;
        }

        return System::run(function () use ($role, $password) {
            $email = Normalizer::emailLower($this->argument('email'));
            $user = User::firstOrCreate(['email' => $email], [
                'name' => $this->argument('name'), 'email_normalized' => Normalizer::email($email),
                'short_code' => User::newShortCode(), 'email_verified_at' => now(), 'locale' => 'az',
            ]);
            $user->password = $password;
            $user->save();
            $user->grantRole($role);
            foreach ((array) $this->option('course') as $slug) {
                $cid = \DB::table('courses')->where('slug', $slug)->value('id');
                if ($cid) {
                    \DB::table('course_instructors')->insertOrIgnore(['course_id' => $cid, 'user_id' => $user->id]);
                }
            }
            Audit::record('user.role_granted', $user, after: ['role' => $role], reason: 'CLI');
            $this->info("{$role} hazırdır: {$email}");

            return self::SUCCESS;
        });
    }
}
