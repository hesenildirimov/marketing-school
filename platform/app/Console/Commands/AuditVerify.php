<?php

namespace App\Console\Commands;

use App\Domain\Audit\Audit;
use App\Support\System;
use Illuminate\Console\Command;

class AuditVerify extends Command
{
    protected $signature = 'platform:audit:verify';

    protected $description = 'Audit log hash zəncirinin bütövlüyünü yoxlayır';

    public function handle(): int
    {
        $broken = System::run(fn () => Audit::verifyChain());
        if ($broken !== null) {
            $this->error("Audit zənciri #{$broken} qeydində qırılıb");

            return self::FAILURE;
        }
        $this->info('Audit zənciri bütövdür');

        return self::SUCCESS;
    }
}
