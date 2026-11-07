@extends('layouts.app')
@section('content')
<nav class="admin-nav" aria-label="{{ __('ui.nav_admin') }}">
  @foreach(['home' => 'admin.home', 'courses' => 'admin.courses.index', 'students' => 'admin.students.index', 'demos' => 'admin.demos.index',
            'grading' => 'admin.grading.index', 'live' => 'admin.live.index', 'mentoring' => 'admin.mentoring.index', 'billing' => 'admin.billing.index',
            'notifications' => 'admin.notifications.index', 'analytics' => 'admin.analytics', 'audit' => 'admin.audit'] as $k => $r)
    @if(Route::has($r) && (auth()->user()->isAdmin() || in_array($k, ['home', 'courses', 'grading', 'live', 'mentoring'])))
      <a href="{{ route($r) }}" @if(request()->routeIs(str_replace('.index', '', $r).'*')) aria-current="page" @endif>{{ __('admin.nav.'.$k) }}</a>
    @endif
  @endforeach
</nav>
@yield('admin')
@endsection
