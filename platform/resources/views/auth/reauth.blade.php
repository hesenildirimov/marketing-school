@extends('layouts.app')
@section('title', __('ui.reauth_title'))
@section('content')
<div class="auth-box card">
  <h1>{{ __('ui.reauth_title') }}</h1>
  <p class="muted">{{ __('ui.reauth_lede') }}</p>
  <form method="POST" action="{{ route('reauth') }}" class="form">
    @csrf
    @include('partials.field', ['name' => 'password', 'type' => 'password', 'label' => __('ui.password'), 'attrs' => 'autocomplete="current-password" required'])
    <div class="btns"><button class="btn" type="submit">{{ __('ui.continue') }}</button></div>
  </form>
</div>
@endsection
