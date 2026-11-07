<?php

namespace App\Domain\Files;

/** ClamAV (clamd) INSTREAM protokolu ilə lokal skan. Fayl serverdən kənara çıxmır. */
final class ClamdScanner implements Scanner
{
    public function __construct(private string $host, private int $port, private int $timeout = 60) {}

    public function scan(string $path): array
    {
        $sock = @stream_socket_client("tcp://{$this->host}:{$this->port}", $errno, $err, 5);
        if (! $sock) {
            return ['status' => 'error', 'result' => "clamd unreachable: {$err}"];
        }
        stream_set_timeout($sock, $this->timeout);
        try {
            fwrite($sock, "zINSTREAM\0");
            $fh = fopen($path, 'rb');
            while (! feof($fh)) {
                $chunk = fread($fh, 8192);
                if ($chunk === '' || $chunk === false) {
                    break;
                }
                fwrite($sock, pack('N', strlen($chunk)).$chunk);
            }
            fclose($fh);
            fwrite($sock, pack('N', 0));
            $reply = trim((string) stream_get_contents($sock), "\0\r\n ");
        } finally {
            fclose($sock);
        }
        if (str_ends_with($reply, 'OK')) {
            return ['status' => 'clean', 'result' => 'OK'];
        }
        if (str_ends_with($reply, 'FOUND')) {
            return ['status' => 'infected', 'result' => mb_substr(trim(substr($reply, strpos($reply, ':') + 1)), 0, 200)];
        }

        return ['status' => 'error', 'result' => mb_substr($reply ?: 'empty reply', 0, 200)];
    }
}
