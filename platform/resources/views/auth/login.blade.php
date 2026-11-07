@extends('layouts.app')
@section('title', __('ui.login_title'))
@section('content')
<div class="auth-box card">
  <h1>{{ __('ui.login_title') }}</h1>
  <form method="POST" action="{{ route('login') }}" class="form">
    @csrf
    @include('partials.field', ['name' => 'email', 'type' => 'email', 'label' => __('ui.email'), 'attrs' => 'autocomplete="email" required'])
    @include('partials.field', ['name' => 'password', 'type' => 'password', 'label' => __('ui.password'), 'attrs' => 'autocomplete="current-password" required'])
    <div class="btns"><button class="btn" type="submit">{{ __('ui.login_btn') }}</button><a href="{{ route('password.forgot') }}">{{ __('ui.forgot_link') }}</a></div>
  </form>
  <p class="hint small">{{ __('ui.no_account') }} <a href="{{ route('register') }}">{{ __('ui.register_link') }}</a> <a href="{{ config('platform.landing_url') }}/#muraciet">{{ __('ui.free_demo_link') }}</a></p>
</div>
@endsection
