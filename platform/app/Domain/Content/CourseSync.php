<?php

namespace App\Domain\Content;

use App\Domain\Audit\Audit;
use App\Models\Course;
use App\Models\Module;
use Illuminate\Support\Facades\DB;

/**
 * Kurs strukturunun tək mənbəyi platformadır (bölmə 3.3). Sahə sahibliyi:
 *   platforma: kurs adı, slug, modul adları/təsvirləri, qiymət, valyuta
 *   landing (Decap CMS): SEO mətnləri, hero, faktlar, FAQ, təlimçi mətnləri, bloq
 * import: landing JSON → platforma (ilkin doldurma; təkrar işə salınanda dublikat yaratmır, platformadakı redaktələri əzmir)
 * export: platforma → landing JSON (yalnız platformaya məxsus sahələr yazılır)
 */
final class CourseSync
{
    public const OWNED = ['name', 'slug', 'modules', 'price', 'currency'];

    public function __construct(private VersionService $versions) {}

    /** @return array{courses_created:int, modules_created:int} */
    public function import(string $dir): array
    {
        $stats = ['courses_created' => 0, 'modules_created' => 0];
        foreach (glob(rtrim($dir, '/').'/*.json') as $file) {
            $data = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
            $tr = self::translations($dir, basename($file));
            DB::transaction(function () use ($data, $tr, &$stats) {
                DB::select('SELECT pg_advisory_xact_lock(?)', [crc32('import:'.$data['slug'])]);
                $course = Course::where('external_id', $data['slug'])->first();
                $new = ! $course;
                if ($new) {
                    $course = Course::create(['slug' => $data['slug'], 'external_id' => $data['slug'],
                        'name' => array_filter(['az' => $data['name'], 'en' => $tr['en']['name'] ?? null, 'ru' => $tr['ru']['name'] ?? null])]);
                    $stats['courses_created']++;
                    // Qiymət (bölmə 1b): hər kurs ayrıca 99 AZN/ay. Sonrakı dəyişikliklər yalnız admin paneldən (prices).
                    if (\Schema::hasTable('prices')) {
                        DB::table('prices')->insert(['course_id' => $course->id, 'amount_qepik' => 9900, 'currency' => 'AZN', 'valid_from' => now()->subMinute(), 'created_at' => now()]);
                    }
                }
                $draft = null;
                foreach (array_values($data['modules'] ?? []) as $i => $m) {
                    $key = 'm'.($i + 1);
                    if (Module::where(['course_id' => $course->id, 'external_key' => $key])->exists()) {
                        continue;
                    }
                    $draft ??= $this->versions->draft($course);
                    $this->versions->addModule($draft,
                        array_filter(['az' => $m['title'], 'en' => $tr['en']['modules'][$i]['title'] ?? null, 'ru' => $tr['ru']['modules'][$i]['title'] ?? null]),
                        array_filter(['az' => $m['text'] ?? '', 'en' => $tr['en']['modules'][$i]['text'] ?? null, 'ru' => $tr['ru']['modules'][$i]['text'] ?? null]), $key);
                    $stats['modules_created']++;
                }
                if ($draft && $new) {
                    $draft->update(['status' => 'published', 'published_at' => now(), 'apply_mode' => 'new_only', 'change_reason' => 'import']);
                    $course->update(['current_version_id' => $draft->id]);
                }
                if ($stats['courses_created'] || $stats['modules_created']) {
                    Audit::record('content.import', $course, after: $stats, reason: 'landing JSON import');
                }
            });
        }
        Manifest::forget();

        return $stats;
    }

    /** Landing-in EN/RU tərcümə faylları (src/_data/i18n/<dil>/kurslar/<fayl>) — varsa. */
    private static function translations(string $dir, string $file): array
    {
        $out = [];
        foreach (['en', 'ru'] as $l) {
            $p = dirname(rtrim($dir, '/'))."/i18n/{$l}/kurslar/{$file}";
            $out[$l] = is_file($p) ? (json_decode(file_get_contents($p), true) ?: []) : [];
        }

        return $out;
    }

    /** @return array<string, array> fayl => dəyişən sahələr */
    public function export(string $dir, bool $write = true): array
    {
        $diffs = [];
        foreach (glob(rtrim($dir, '/').'/*.json') as $file) {
            $data = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
            $course = Course::where('external_id', $data['slug'])->first();
            if (! $course || ! $course->current_version_id) {
                continue;
            }
            $m = Manifest::for($course->current_version_id);
            $owned = [
                'name' => tr_field($course->name, 'az'),
                'slug' => $course->slug,
                'modules' => $m->modules->map(fn ($x) => ['title' => tr_field($x->title, 'az'), 'text' => tr_field($x->description, 'az')])->values()->all(),
            ];
            if (\Schema::hasTable('prices') && ($p = DB::table('prices')->where('course_id', $course->id)->where('valid_from', '<=', now())->orderByDesc('valid_from')->first())) {
                $owned['price'] = rtrim(rtrim(number_format($p->amount_qepik / 100, 2, '.', ''), '0'), '.');
                $owned['currency'] = $p->currency;
            }
            $changed = array_keys(array_filter($owned, fn ($v, $k) => ($data[$k] ?? null) !== $v, ARRAY_FILTER_USE_BOTH));
            if ($changed) {
                $diffs[basename($file)] = $changed;
                if ($write) {
                    file_put_contents($file, json_encode(array_merge($data, $owned), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
                }
            }
            // EN/RU: platformada tərcümə varsa, kurs adı və modullar landing tərcümə fayllarına yazılır (tək mənbə — platforma)
            foreach (['en', 'ru'] as $l) {
                $p = dirname(rtrim($dir, '/'))."/i18n/{$l}/kurslar/".basename($file);
                if (! is_file($p)) {
                    continue;
                }
                $trData = json_decode(file_get_contents($p), true) ?: [];
                $trOwned = [];
                if (! empty($course->name[$l])) {
                    $trOwned['name'] = $course->name[$l];
                }
                $mods = $m->modules->map(fn ($x) => ['title' => $x->title[$l] ?? null, 'text' => $x->description[$l] ?? null])->values()->all();
                if ($mods && collect($mods)->every(fn ($x) => $x['title'])) {
                    $trOwned['modules'] = array_map(fn ($x) => ['title' => $x['title'], 'text' => (string) $x['text']], $mods);
                }
                $trChanged = array_keys(array_filter($trOwned, fn ($v, $k) => ($trData[$k] ?? null) !== $v, ARRAY_FILTER_USE_BOTH));
                if ($trChanged) {
                    $diffs["i18n/{$l}/".basename($file)] = $trChanged;
                    if ($write) {
                        file_put_contents($p, json_encode(array_merge($trData, $trOwned), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
                    }
                }
            }
        }

        return $diffs;
    }
}
