<?php

namespace App\Console\Commands;

use App\Domain\Audit\Audit;
use App\Support\System;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Birdəfəlik domen siyahısının yenilənməsi (məs. github.com/disposable-email-domains siyahısı). */
class DisposableImport extends Command
{
    protected $signature = 'platform:disposable:import {file} {--source=import}';

    protected $description = 'Birdəfəlik e-mail domenlərini fayldan əlavə edir (hər sətirdə bir domen)';

    public function handle(): int
    {
        $lines = file($this->argument('file'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $domains = array_values(array_unique(array_filter(array_map(fn ($l) => strtolower(trim($l)), $lines),
            fn ($d) => preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/', $d))));
        $n = System::run(function () use ($domains) {
            $n = 0;
            foreach (array_chunk($domains, 500) as $chunk) {
                $n += DB::table('disposable_email_domains')
                    ->insertOrIgnore(array_map(fn ($d) => ['domain' => $d, 'source' => $this->option('source')], $chunk));
            }
            Audit::record('demo.disposable_import', null, after: ['added' => $n], entityType: 'disposable_email_domains');

            return $n;
        });
        $this->info("{$n} yeni domen əlavə olundu");

        return self::SUCCESS;
    }
}
