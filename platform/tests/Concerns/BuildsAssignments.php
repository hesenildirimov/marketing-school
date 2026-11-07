<?php

namespace Tests\Concerns;

use App\Domain\Assessment\AssignmentService;
use App\Domain\Content\VersionService;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\Module;
use App\Models\Submission;
use App\Models\User;
use App\Support\DbContext;

trait BuildsAssignments
{
    /** Kursa tapşırıqlar əlavə edir (yeni versiya, hamıya tətbiq). @param array<int,array{0:int,1:bool}> $specs [max, mandatory] */
    protected function addAssignments(Course $course, array $specs, string $apply = 'all'): array
    {
        $vs = app(VersionService::class);
        $svc = app(AssignmentService::class);
        $admin = $this->makeUser(['email' => 'asg-'.bin2hex(random_bytes(3)).'@example.com'], ['admin']);
        $draft = $vs->draft($course->fresh());
        $module = Module::where('course_id', $course->id)->orderBy('id')->first();
        $out = [];
        foreach ($specs as $i => [$max, $mandatory]) {
            $out[] = $svc->create($draft, $module, ['az' => "Tapşırıq $i"], ['az' => "Keys $i"], $max, $mandatory, null, $admin);
        }
        $vs->publish($draft, $apply, $apply === 'all' ? 'tapşırıqlar əlavə olundu' : null, $admin);

        return $out;
    }

    protected function submitAs(User $u, Assignment $a, string $body = 'Cavab'): Submission
    {
        DbContext::actAs($u->id);
        $s = Submission::updateOrCreate(['user_id' => $u->id, 'assignment_id' => $a->id],
            ['course_id' => $a->course_id, 'body_html' => "<p>{$body}</p>", 'status' => 'submitted', 'submitted_at' => now()]);
        DbContext::actAs(null);

        return $s;
    }

    protected function instructorFor(Course $c): User
    {
        $t = $this->makeUser(['email' => 'teach-'.bin2hex(random_bytes(3)).'@example.com', 'name' => 'Həsənalı İldırımov'], ['instructor']);
        \DB::table('course_instructors')->insert(['course_id' => $c->id, 'user_id' => $t->id]);

        return $t;
    }
}
