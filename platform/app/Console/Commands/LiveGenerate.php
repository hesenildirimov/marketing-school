<?php

namespace App\Console\Commands;

use App\Domain\Live\LiveService;
use App\Models\Course;
use App\Support\System;
use Illuminate\Console\Command;

class LiveGenerate extends Command
{
    protected $signature = 'platform:live:generate {--months=3}';

    protected $description = 'Hər kurs üçün gələcək ayların 2-ci və 4-cü şənbə görüşlərini yaradır (idempotent)';

    public function handle(LiveService $live): int
    {
        $n = System::run(fn () => Course::all()->sum(fn ($c) => $live->generate($c, (int) $this->option('months'))));
        $this->info("yeni görüş: {$n}");

        return self::SUCCESS;
    }
}
