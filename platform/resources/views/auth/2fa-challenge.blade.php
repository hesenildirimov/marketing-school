@extends('layouts.app')
@section('title', __('ui.2fa_challenge_title'))
@section('content')
<div class="auth-box card">
  <h1>{{ __('ui.2fa_challenge_title') }}</h1>
  <p class="muted">{{ __('ui.2fa_challenge_lede') }}</p>
  <form method="POST" action="{{ route('2fa.challenge') }}" class="form">
    @csrf
    @include('partials.field', ['name' => 'code', 'label' => __('ui.2fa_code'), 'attrs' => 'inputmode="numeric" autocomplete="one-time-code" autofocus'])
    @include('partials.field', ['name' => 'recovery', 'label' => __('ui.2fa_recovery'), 'attrs' => 'autocomplete="off"'])
    <div class="btns"><button class="btn" type="submit">{{ __('ui.2fa_btn') }}</button></div>
  </form>
</div>
@endsection
