@extends('layouts.app')
@section('title', __('ui.register_title'))
@section('content')
<div class="auth-box card">
  <h1>{{ __('ui.register_title') }}</h1>
  <p class="muted">{{ __('ui.register_lede') }}</p>
  <form method="POST" action="{{ route('register') }}" class="form">
    @csrf
    <div class="hp" aria-hidden="true"><label for="f-website">Website</label><input id="f-website" name="website" tabindex="-1" autocomplete="off"></div>
    @include('partials.field', ['name' => 'name', 'label' => __('ui.name'), 'attrs' => 'autocomplete="name" required maxlength="120"'])
    @include('partials.field', ['name' => 'email', 'type' => 'email', 'label' => __('ui.email'), 'attrs' => 'autocomplete="email" required'])
    @include('partials.field', ['name' => 'phone', 'type' => 'tel', 'label' => __('ui.phone'), 'attrs' => 'autocomplete="tel" inputmode="tel" required placeholder="'.e(__('ui.phone_placeholder')).'"'])
    <label class="check"><input type="checkbox" name="consent" value="1" required> <span>{{ __('ui.consent') }} <a href="{{ route('legal', 'privacy') }}">↗</a></span></label>
    @error('consent')<p class="err">{{ $message }}</p>@enderror
    <div class="btns"><button class="btn" type="submit">{{ __('ui.register_btn') }}</button></div>
  </form>
</div>
@endsection
