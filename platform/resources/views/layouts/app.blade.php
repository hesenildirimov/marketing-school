<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'Marketing School') · Marketing School</title>
<link rel="icon" href="{{ url('/favicon.svg') }}" type="image/svg+xml">
<link rel="stylesheet" href="{{ passet('css/tokens.css') }}">
<link rel="stylesheet" href="{{ passet('css/app.css') }}">
@stack('head')
</head>
<body>
<a class="skip" href="#main">{{ __('ui.skip') }}</a>
<header class="site-head">
  <div class="wrap head-row">
    <a class="brand" href="{{ auth()->check() ? route('dashboard') : config('platform.landing_url') }}">@include('partials.logo')<span>Marketing&nbsp;School</span></a>
    <nav class="nav" aria-label="{{ __('ui.nav_courses') }}">
      @auth
        <a href="{{ route('dashboard') }}" @if(request()->routeIs('dashboard', 'course.*', 'lesson.*')) aria-current="page" @endif>{{ __('ui.nav_courses') }}</a>
        @yield('nav-extra')
        @if(auth()->user()->isStaff())<a href="{{ route('admin.home') }}" @if(request()->routeIs('admin.*')) aria-current="page" @endif>{{ __('ui.nav_admin') }}</a>@endif
        @if(Route::has('notifications.index'))@php($unread = \App\Http\Controllers\NotificationController::unread(auth()->id()))<a href="{{ route('notifications.index') }}" aria-label="{{ __('privacy.notifications') }}{{ $unread ? ' ('.$unread.')' : '' }}">🔔@if($unread)<span class="badge">{{ $unread }}</span>@endif</a>@endif
        <a href="{{ route('account.sessions') }}" @if(request()->routeIs('account.*')) aria-current="page" @endif>{{ __('ui.nav_account') }}</a>
        <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit">{{ __('ui.nav_logout') }}</button></form>
      @else
        <a href="{{ route('login') }}">{{ __('ui.nav_login') }}</a>
      @endauth
      <span class="lang" aria-label="{{ __('ui.lang_label') }}">
        @foreach(config('platform.locales') as $l)<a href="{{ request()->fullUrlWithQuery(['lang' => $l]) }}" hreflang="{{ $l }}" lang="{{ $l }}" @if(app()->getLocale() === $l) aria-current="true" @endif>{{ strtoupper($l) }}</a>@endforeach
      </span>
    </nav>
  </div>
</header>
<main id="main"><div class="wrap">
  @if(session('status'))<div class="alert" role="status">{{ session('status') }}</div>@endif
  @yield('content')
</div></main>
<footer class="site-foot"><div class="wrap">{{ __('ui.footer') }}</div></footer>
@stack('scripts')
</body>
</html>
