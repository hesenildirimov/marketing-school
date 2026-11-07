@extends('layouts.app')
@section('title', __('ui.2fa_setup_title'))
@section('content')
<div class="auth-box card">
  <h1>{{ __('ui.2fa_setup_title') }}</h1>
  <p class="muted">{{ __('ui.2fa_setup_lede') }}</p>
  <div class="qr">{!! $qr !!}</div>
  <p class="small">{{ __('ui.2fa_secret') }}: <code>{{ trim(chunk_split($secret, 4, ' ')) }}</code></p>
  <form method="POST" action="{{ route('2fa.challenge') }}" class="form">
    @csrf
    @include('partials.field', ['name' => 'code', 'label' => __('ui.2fa_code'), 'attrs' => 'inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]{6,7}" required'])
    <div class="btns"><button class="btn" type="submit">{{ __('ui.2fa_btn') }}</button></div>
  </form>
</div>
@endsection
