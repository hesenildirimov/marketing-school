@extends('layouts.app')
@section('title', __('ui.reset_title'))
@section('content')
<div class="auth-box card">
  @if($valid)
  <h1>{{ __('ui.reset_title') }}</h1>
  <form method="POST" action="{{ route('password.reset') }}" class="form">
    @csrf
    <input type="hidden" name="t" value="{{ $token }}">
    @include('partials.field', ['name' => 'password', 'type' => 'password', 'label' => __('ui.password'), 'attrs' => 'autocomplete="new-password" required'])
    <p class="hint small">{{ __('ui.password_hint', ['min' => config('platform.auth.password_min_length')]) }}</p>
    @include('partials.field', ['name' => 'password_confirmation', 'type' => 'password', 'label' => __('ui.password_confirm'), 'attrs' => 'autocomplete="new-password" required'])
    <div class="btns"><button class="btn" type="submit">{{ __('ui.reset_btn') }}</button></div>
  </form>
  @else
  <h1>{{ __('ui.link_invalid_title') }}</h1><p>{{ __('ui.link_invalid') }}</p>
  @error('t')<p class="err">{{ $message }}</p>@enderror
  <div class="btns"><a class="btn" href="{{ route('password.forgot') }}">{{ __('ui.request_new_link') }}</a></div>
  @endif
</div>
@endsection
