<?php

namespace App\Console\Commands;

use App\Domain\Live\LiveService;
use App\Support\System;
use Illuminate\Console\Command;

class Reminders extends Command
{
    protected $signature = 'platform:reminders';

    protected $description = 'Canlı görüş xatırlatmalarını planlaşdırır (24 saat və 1 saat əvvəl)';

    public function handle(LiveService $live): int
    {
        $n = System::run(fn () => $live->scheduleReminders());
        $this->line("xatırlatma: {$n}");

        return self::SUCCESS;
    }
}
