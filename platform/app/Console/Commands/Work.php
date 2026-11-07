<?php

namespace App\Console\Commands;

use App\Domain\Notifications\NotificationSender;
use App\Domain\Notifications\Outbox;
use Illuminate\Console\Command;

/** Daimi worker: outbox və e-mail növbəsini emal edir. Docker-də `worker` konteynerində işləyir. */
class Work extends Command
{
    protected $signature = 'platform:work {--once : bir dövr işlə və çıx} {--sleep=5}';

    protected $description = 'Outbox və bildiriş növbəsinin emalı';

    public function handle(NotificationSender $sender): int
    {
        do {
            $o = Outbox::process();
            $m = $sender->run();
            if ($this->option('once')) {
                $this->line("outbox={$o} mail={$m}");
                break;
            }
            if ($o + $m === 0) {
                sleep((int) $this->option('sleep'));
            }
        } while (true);

        return self::SUCCESS;
    }
}
