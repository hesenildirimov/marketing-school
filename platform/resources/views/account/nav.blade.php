<nav class="btns" aria-label="{{ __('ui.nav_account') }}">
  <a class="btn btn-sm {{ request()->routeIs('account.sessions') ? '' : 'btn-ghost' }}" href="{{ route('account.sessions') }}">{{ __('ui.sessions_title') }}</a>
  @if(Route::has('account.privacy'))<a class="btn btn-sm {{ request()->routeIs('account.privacy') ? '' : 'btn-ghost' }}" href="{{ route('account.privacy') }}">{{ __('privacy.nav') }}</a>@endif
</nav>
