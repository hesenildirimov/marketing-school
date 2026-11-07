<?php

namespace App\Console\Commands;

use App\Domain\Content\CourseSync;
use App\Support\System;
use Illuminate\Console\Command;

class CourseImport extends Command
{
    protected $signature = 'platform:courses:import {--path=../src/_data/kurslar}';

    protected $description = 'Landing kurs JSON-larını platformaya import edir (idempotent)';

    public function handle(CourseSync $sync): int
    {
        $s = System::run(fn () => $sync->import($this->option('path')));
        $this->info("kurs: +{$s['courses_created']}, modul: +{$s['modules_created']}");

        return self::SUCCESS;
    }
}
