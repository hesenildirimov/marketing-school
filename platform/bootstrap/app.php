<?php

use App\Http\Middleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware as MW;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/platform/up',
    )
    ->withMiddleware(function (MW $middleware): void {
        $middleware->prepend(Middleware\VerifyProxySignature::class);
        $middleware->append(Middleware\SecurityHeaders::class);
        // RLS konteksti route-model binding-dən ƏVVƏL qurulmalıdır (əks halda RLS cədvəllərindəki modellər tapılmır).
        $middleware->web(remove: [SubstituteBindings::class],
            append: [Middleware\SetDbContext::class, SubstituteBindings::class, Middleware\SetLocale::class]);
        $middleware->alias([
            'session.valid' => Middleware\EnsureValidSession::class,
            '2fa' => Middleware\RequireTwoFactor::class,
            'role' => Middleware\EnsureRole::class,
            'reauth' => Middleware\RequireRecentAuth::class,
        ]);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
        // Webhook-lar imza ilə qorunur, CSRF tokeni yoxdur (provayder server-to-server göndərir).
        // Demo forması statik landing-dən gəlir: tokeni yoxdur, əvəzində Sec-Fetch-Site/Origin yoxlanılır (DemoController).
        $middleware->validateCsrfTokens(except: ['platform/api/payments/webhook/*', 'platform/api/demo/register']);
        $middleware->encryptCookies(except: ['ms_lang']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('platform/api/*') || $request->expectsJson(),
        );
    })->create();
