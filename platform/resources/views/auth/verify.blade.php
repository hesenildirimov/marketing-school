@extends('layouts.app')
@section('title', __('ui.verify_title'))
@section('content')
<div class="auth-box card">
  @if($valid)
    <h1>{{ __('ui.verify_title') }}</h1>
    <form method="POST" action="{{ route('verify.complete') }}" class="form">
      @csrf
      <input type="hidden" name="t" value="{{ $token }}">
      @include('partials.field', ['name' => 'password', 'type' => 'password', 'label' => __('ui.password'), 'attrs' => 'autocomplete="new-password" required minlength="'.config('platform.auth.password_min_length').'"'])
      <p class="hint small">{{ __('ui.password_hint', ['min' => config('platform.auth.password_min_length')]) }}</p>
      @include('partials.field', ['name' => 'password_confirmation', 'type' => 'password', 'label' => __('ui.password_confirm'), 'attrs' => 'autocomplete="new-password" required'])
      <div class="btns"><button class="btn" type="submit">{{ __('ui.verify_btn') }}</button></div>
    </form>
  @else
    <h1>{{ __('ui.link_invalid_title') }}</h1>
    <p>{{ __('ui.link_invalid') }}</p>
    @error('t')<p class="err">{{ $message }}</p>@enderror
    <div class="btns"><a class="btn" href="{{ route('login') }}">{{ __('ui.nav_login') }}</a>@if(Route::has('demo.resend'))<a class="btn btn-ghost" href="{{ route('demo.resend.form') }}">{{ __('ui.request_new_link') }}</a>@endif</div>
  @endif
</div>
@endsection
