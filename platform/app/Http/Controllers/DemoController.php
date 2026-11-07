<?php

namespace App\Http\Controllers;

use App\Domain\Access\AccessPolicy;
use App\Domain\Demo\DemoService;
use App\Models\Course;
use App\Models\User;
use App\Support\Normalizer;
use Illuminate\Http\Request;

class DemoController extends Controller
{
    public function form(Request $request)
    {
        return view('demo.form', ['courses' => Course::orderBy('id')->get(), 'selected' => $request->query('kurs')]);
    }

    /**
     * Landing formasının endpoint-i. Eyni origin (marketingschool.az) olduğu üçün CORS yoxdur;
     * CSRF tokeni statik formada olmadığından Sec-Fetch-Site/Origin yoxlanılır.
     * Spam: honeypot, minimum doldurma vaxtı, IP + e-mail üzrə rate limit. Xarici CAPTCHA yoxdur (1c-9).
     */
    public function register(Request $request, DemoService $demo)
    {
        $this->assertSameOrigin($request);
        $lang = in_array($request->input('lang'), config('platform.locales'), true) ? $request->input('lang') : 'az';
        app()->setLocale($lang);
        $back = fn (array $errors) => redirect()->route('demo.form', ['lang' => $lang, 'kurs' => $request->input('kurs')])
            ->withInput($request->except(['bot-field', 'website']))->withErrors($errors);

        // Honeypot və ya çox tez doldurma: bot — cavab uğurlu kimi görünür, heç nə yaranmır.
        $ts = (int) $request->input('ts');
        $elapsed = $ts > 0 ? (int) floor(microtime(true)) - intdiv($ts, 1000) : -1;
        if ($request->filled('bot-field') || $request->filled('website') || $elapsed < config('platform.demo.min_fill_seconds') || $elapsed > 86400) {
            return redirect()->route('check-email', ['lang' => $lang]);
        }
        $data = $request->validate([
            'ad' => 'required|string|min:2|max:120',
            'email' => 'required|email:rfc|max:254',
            'tel' => 'required|string|max:30',
            'kurs' => 'required|string|exists:courses,slug',
            'consent' => 'accepted',
        ]);
        $phone = Normalizer::phone($data['tel']);
        if (! $phone) {
            return $back(['tel' => __('ui.phone_invalid')]);
        }
        if (DemoService::isDisposable($data['email'])) {
            return $back(['email' => __('demo.disposable')]);
        }
        $demo->register($data['ad'], $data['email'], $phone, Course::where('slug', $data['kurs'])->firstOrFail(), $lang, $request->ip());

        return redirect()->route('check-email', ['lang' => $lang])->with('demo_note', __('demo.check_note'));
    }

    public function resendForm()
    {
        return view('demo.resend');
    }

    public function resend(Request $request, DemoService $demo)
    {
        $data = $request->validate(['email' => 'required|string|max:254']);
        $user = User::where('email', Normalizer::emailLower($data['email']))->first();
        if ($user) {
            $demo->resend($user);
        }

        return back()->with('status', __('demo.resend_sent'));
    }

    /** Hesabı olan, amma demonu hələ istifadə etməmiş istifadəçi (məs. birbaşa qeydiyyat). */
    public function start(Request $request, Course $course, DemoService $demo, AccessPolicy $access)
    {
        $user = $request->user();
        abort_if($access->demoClaim($user) || $access->hasPaidAccess($user, $course), 403);
        $claim = $demo->startForUser($user, $course);
        if (! $claim) {
            return back()->with('status', __('demo.used_elsewhere'));
        }

        return redirect()->route('course.show', $course);
    }

    private function assertSameOrigin(Request $request): void
    {
        $site = $request->headers->get('Sec-Fetch-Site');
        if ($site !== null) {
            abort_unless(in_array($site, ['same-origin', 'none'], true), 403);

            return;
        }
        $origin = $request->headers->get('Origin');
        if ($origin !== null) {
            abort_unless(rtrim($origin, '/') === rtrim(config('platform.landing_url'), '/'), 403);
        }
    }
}
