<?php

namespace App\Domain\Certificates;

use App\Models\Course;
use App\Models\User;

/** Outbox handler: `diploma.evaluate` — məs. versiya dəyişikliyindən sonra toplu yenidən hesablama. */
final class EvaluateHandler
{
    public function __construct(private DiplomaService $d) {}

    public function handle(array $p): void
    {
        $u = User::find($p['user_id']);
        $c = Course::find($p['course_id']);
        if ($u && $c) {
            $this->d->evaluate($u, $c);
        }
    }
}
