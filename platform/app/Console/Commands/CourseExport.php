<?php

namespace App\Console\Commands;

use App\Domain\Content\CourseSync;
use App\Support\System;
use Illuminate\Console\Command;

/** CI-da: `--check` landing ilə platforma arasında uyğunsuzluq varsa xəta qaytarır. */
class CourseExport extends Command
{
    protected $signature = 'platform:courses:export {--path=../src/_data/kurslar} {--check}';

    protected $description = 'Platformaya məxsus kurs sahələrini landing JSON-una yazır';

    public function handle(CourseSync $sync): int
    {
        $diffs = System::run(fn () => $sync->export($this->option('path'), ! $this->option('check')));
        foreach ($diffs as $file => $fields) {
            $this->line("{$file}: ".implode(', ', $fields));
        }
        if ($this->option('check') && $diffs) {
            $this->error('Landing kurs məlumatı platformadan fərqlənir: php artisan platform:courses:export');

            return self::FAILURE;
        }
        $this->info($diffs ? 'yeniləndi' : 'uyğundur');

        return self::SUCCESS;
    }
}
