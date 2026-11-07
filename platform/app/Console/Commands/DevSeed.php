<?php

namespace App\Console\Commands;

use App\Domain\Assessment\AssignmentService;
use App\Domain\Audit\Audit;
use App\Domain\Content\CourseSync;
use App\Domain\Content\VersionService;
use App\Domain\Live\LiveService;
use App\Models\Course;
use App\Models\DemoClaim;
use App\Models\Enrollment;
use App\Models\Module;
use App\Models\User;
use App\Models\VideoAsset;
use App\Support\Normalizer;
use App\Support\System;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Lokal/staging nümunə məlumatı (production-da işləmir): landing kurslarının importu, hər modula 2 dərs (fake video),
 * tapşırıqlar, admin/təlimçi/ödənişli tələbə/demo tələbə. Staging-də real istifadəçi məlumatı OLMUR (bölmə 6).
 */
class DevSeed extends Command
{
    protected $signature = 'platform:dev-seed {--path=../src/_data/kurslar} {--password=Secret12345} {--loadtest=0 : yük testi üçün loadtest+N@example.test tələbələri}';

    protected $description = 'Lokal və staging üçün nümunə məlumatı';

    public function handle(CourseSync $sync, VersionService $vs, AssignmentService $as): int
    {
        if (app()->environment('production')) {
            $this->error('production-da icazə verilmir');

            return self::FAILURE;
        }

        return System::run(function () use ($sync, $vs, $as) {
            $sync->import($this->option('path'));
            $mk = fn (string $email, string $name, array $roles, ?string $phone = null) => tap(User::firstOrCreate(['email' => $email], [
                'name' => $name, 'email_normalized' => Normalizer::email($email), 'phone_e164' => $phone, 'locale' => 'az',
                'short_code' => User::newShortCode(), 'email_verified_at' => now(), 'password' => $this->option('password'),
            ]), fn ($u) => array_map(fn ($r) => $u->grantRole($r), $roles));
            $admin = $mk('admin@example.test', 'Admin', ['admin']);
            $teacher = $mk('teacher@example.test', 'Həsənalı İldırımov', ['instructor']);
            foreach (Course::orderBy('id')->get() as $course) {
                DB::table('course_instructors')->insertOrIgnore(['course_id' => $course->id, 'user_id' => $teacher->id]);
                if (DB::table('lessons')->where('course_id', $course->id)->exists()) {
                    continue;
                }
                $draft = $vs->draft($course);
                foreach (Module::where('course_id', $course->id)->orderBy('id')->get() as $i => $m) {
                    foreach ([1, 2] as $j) {
                        $l = $vs->addLesson($draft, $m, ['az' => 'Dərs '.($i + 1).".$j", 'en' => 'Lesson '.($i + 1).".$j"], ['az' => 'Nümunə dərs mətni.']);
                        $v = VideoAsset::create(['provider' => 'fake', 'provider_video_id' => "dev-{$course->id}-{$l->id}", 'status' => 'ready', 'duration_seconds' => 540]);
                        $vs->revise($l, ['video_asset_id' => $v->id, 'chapters' => [['t' => 0, 'title' => ['az' => 'Giriş']], ['t' => 120, 'title' => ['az' => 'Əsas hissə']]]]);
                    }
                    $as->create($draft, $m, ['az' => 'Modul '.($i + 1).' tapşırığı'], ['az' => 'Real keys əsasında qısa təhlil yazın.'], 10, true, null, $admin);
                }
                $vs->publish($draft, 'new_only', null, $admin);
            }
            $paid = $mk('student@example.test', 'Aysel Məmmədova', ['student'], '+994501112233');
            $first = Course::orderBy('id')->first();
            Enrollment::updateOrCreate(['user_id' => $paid->id, 'course_id' => $first->id],
                ['course_version_id' => $first->fresh()->current_version_id, 'paid_until' => now()->addMonth()]);
            $demo = $mk('demo@example.test', 'Demo İstifadəçi', ['student'], '+994502223344');
            DemoClaim::firstOrCreate(['user_id' => $demo->id], ['course_id' => $first->id, 'email_normalized' => 'demo@example.test',
                'phone_e164' => '+994502223344', 'started_at' => now()->startOfSecond(), 'expires_at' => now()->startOfSecond()->addHours(24), 'email_confirmed_at' => now()]);
            for ($n = 1; $n <= (int) $this->option('loadtest'); $n++) {
                $lt = $mk("loadtest+{$n}@example.test", "Load Test {$n}", ['student']);
                Enrollment::updateOrCreate(['user_id' => $lt->id, 'course_id' => $first->id],
                    ['course_version_id' => $first->fresh()->current_version_id, 'paid_until' => now()->addMonth()]);
            }
            app(LiveService::class)->generate($first);
            Audit::record('dev.seed', null, after: ['courses' => Course::count()]);
            $this->info('admin@example.test / teacher@example.test / student@example.test / demo@example.test — parol: '.$this->option('password'));

            return self::SUCCESS;
        });
    }
}
