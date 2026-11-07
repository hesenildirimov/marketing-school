<?php

namespace App\Domain\Assessment;

/** İstifadəçi mətnlərinin (tapşırıq cavabı, rəy) XSS-ə qarşı sanitizasiyası — HTMLPurifier, ciddi ağ siyahı. */
final class Sanitizer
{
    public static function html(?string $input): string
    {
        static $purifier = null;
        if (! $purifier) {
            $cfg = \HTMLPurifier_Config::createDefault();
            $cfg->set('HTML.Allowed', 'p,br,strong,b,em,i,u,ul,ol,li,blockquote,a[href]');
            $cfg->set('URI.AllowedSchemes', ['https' => true, 'http' => true, 'mailto' => true]);
            $cfg->set('HTML.TargetBlank', true);
            $cfg->set('HTML.Nofollow', true);
            $cfg->set('Cache.DefinitionImpl', null);
            $purifier = new \HTMLPurifier($cfg);
        }
        $input = (string) $input;
        // Düz mətn (textarea) → abzaslar; HTML gəlibsə, purifier təmizləyir
        if ($input !== strip_tags($input)) {
            return $purifier->purify($input);
        }

        return $purifier->purify(implode('', array_map(fn ($p) => '<p>'.nl2br(e(trim($p))).'</p>',
            array_filter(preg_split("/\R{2,}/", trim($input)), fn ($p) => trim($p) !== ''))));
    }
}
