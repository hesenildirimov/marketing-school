<?php

namespace App\Http\Middleware;

use App\Support\DbContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Hər sorğuda RLS konteksti: autentifikasiya olunmuş istifadəçinin ID-si və ya boş. */
class SetDbContext
{
    public function handle(Request $request, Closure $next): Response
    {
        DbContext::actAs(Auth::id());

        return $next($request);
    }
}
