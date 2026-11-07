<?php

namespace App\Domain\Files;

/**
 * Fayl tipinin MƏZMUNLA yoxlanması (bölmə 2.5). Hər tədbirin qarşısını aldığı risk:
 * - uzantı + magic bytes (finfo) birlikdə: zərərli faylın başqa tip kimi gizlədilməsi;
 * - makrolu formatlar (.docm/.xlsm/.pptm və vbaProject.bin) rədd: zərərli makrolar;
 * - OOXML arxivində fayl sayı / açılış ölçüsü / sıxılma nisbəti limiti: ZIP bomb;
 * - şəkillər getimagesize ilə: poliqlot/saxta şəkil.
 */
final class FileValidator
{
    public const TYPES = [
        'pdf' => ['application/pdf'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip', 'application/octet-stream'],
        'png' => ['image/png'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
    ];

    private const OOXML_MAIN = ['docx' => 'word/', 'xlsx' => 'xl/', 'pptx' => 'ppt/'];

    public const MAX_ENTRIES = 2000;

    public const MAX_UNCOMPRESSED = 100 * 1024 * 1024;

    public const MAX_RATIO = 100;

    /** @return array{ext:string, mime:string} */
    public static function validate(string $path, string $originalName): array
    {
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (in_array($ext, ['docm', 'xlsm', 'pptm', 'dotm', 'xltm', 'potm'], true)) {
            throw new FileRejected('macro');
        }
        if (! isset(self::TYPES[$ext])) {
            throw new FileRejected('type');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream';
        if (! in_array($mime, self::TYPES[$ext], true)) {
            throw new FileRejected('type');
        }
        $head = (string) file_get_contents($path, false, null, 0, 8);
        match ($ext) {
            'pdf' => str_starts_with($head, '%PDF-') ?: throw new FileRejected('type'),
            'png' => (str_starts_with($head, "\x89PNG\r\n\x1a\n") && @getimagesize($path)) ?: throw new FileRejected('type'),
            'jpg', 'jpeg' => (str_starts_with($head, "\xFF\xD8\xFF") && @getimagesize($path)) ?: throw new FileRejected('type'),
            default => self::checkOoxml($path, $ext),
        };

        return ['ext' => $ext, 'mime' => $mime];
    }

    private static function checkOoxml(string $path, string $ext): bool
    {
        if (! str_starts_with((string) file_get_contents($path, false, null, 0, 4), "PK\x03\x04")) {
            throw new FileRejected('type');
        }
        $zip = new \ZipArchive;
        if ($zip->open($path, \ZipArchive::RDONLY) !== true) {
            throw new FileRejected('type');
        }
        try {
            if ($zip->numFiles > self::MAX_ENTRIES) {
                throw new FileRejected('zip_bomb');
            }
            $total = 0;
            $hasMain = false;
            $hasTypes = false;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $st = $zip->statIndex($i);
                $name = $st['name'];
                $total += $st['size'];
                if ($total > self::MAX_UNCOMPRESSED || ($st['comp_size'] > 0 && $st['size'] / $st['comp_size'] > self::MAX_RATIO && $st['size'] > 1024 * 1024)) {
                    throw new FileRejected('zip_bomb');
                }
                if (str_contains(strtolower($name), 'vbaproject.bin') || str_contains(strtolower($name), 'vbadata')) {
                    throw new FileRejected('macro');
                }
                if (str_contains($name, '..') || str_starts_with($name, '/')) {
                    throw new FileRejected('type');
                }
                $hasTypes = $hasTypes || $name === '[Content_Types].xml';
                $hasMain = $hasMain || str_starts_with($name, self::OOXML_MAIN[$ext]);
            }
            if (! $hasTypes || ! $hasMain) {
                throw new FileRejected('type');
            }
            $ct = (string) $zip->getFromName('[Content_Types].xml', 1024 * 1024);
            if (stripos($ct, 'macroEnabled') !== false || stripos($ct, 'vbaProject') !== false) {
                throw new FileRejected('macro');
            }
        } finally {
            $zip->close();
        }

        return true;
    }
}
