@extends('layouts.app')
@section('title', __('ui.forgot_title'))
@section('content')
<div class="auth-box card">
  <h1>{{ __('ui.forgot_title') }}</h1>
  <p class="muted">{{ __('ui.forgot_lede') }}</p>
  <form method="POST" action="{{ route('password.forgot') }}" class="form">
    @csrf
    @include('partials.field', ['name' => 'email', 'type' => 'email', 'label' => __('ui.email'), 'attrs' => 'autocomplete="email" required'])
    <div class="btns"><button class="btn" type="submit">{{ __('ui.forgot_btn') }}</button><a href="{{ route('login') }}">{{ __('ui.back') }}</a></div>
  </form>
</div>
@endsection
