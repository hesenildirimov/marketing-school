<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;

/**
 * Real paralel proseslərlə race condition testi (pcntl_fork). Hər uşaq proses öz DB bağlantısını açır
 * və eyni anda (barrier) başlayır. Nəticələr pipe ilə valideynə qaytarılır.
 */
final class Parallel
{
    /** @return array<int, mixed> */
    public static function run(int $n, callable $fn): array
    {
        $start = microtime(true) + 0.4;
        $pipes = [];
        $pids = [];
        for ($i = 0; $i < $n; $i++) {
            $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
            $pid = pcntl_fork();
            if ($pid === 0) {
                fclose($pair[0]);
                foreach (array_keys(config('database.connections')) as $c) {
                    DB::purge($c);
                }
                time_sleep_until($start);
                try {
                    $out = ['ok' => true, 'v' => $fn($i)];
                } catch (\Throwable $e) {
                    $out = ['ok' => false, 'v' => get_class($e).': '.$e->getMessage()];
                }
                fwrite($pair[1], serialize($out));
                fclose($pair[1]);
                posix_kill(getmypid(), SIGKILL); // shutdown handler-ləri (test framework) işə düşməsin
            }
            fclose($pair[1]);
            $pipes[$i] = $pair[0];
            $pids[$i] = $pid;
        }
        $results = [];
        foreach ($pipes as $i => $p) {
            $data = stream_get_contents($p);
            fclose($p);
            pcntl_waitpid($pids[$i], $status);
            $r = $data ? unserialize($data) : ['ok' => false, 'v' => 'no output'];
            $results[$i] = $r['ok'] ? $r['v'] : 'ERR '.$r['v'];
        }
        foreach (array_keys(config('database.connections')) as $c) {
            DB::purge($c);
        }

        return $results;
    }
}
