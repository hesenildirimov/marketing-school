@extends('layouts.app')
@section('title', __('demo.title'))
@section('content')
<div class="auth-box card">
  <h1>{{ __('demo.title') }}</h1>
  <p class="muted">{{ __('demo.lede') }}</p>
  <form method="POST" action="{{ route('demo.register') }}" class="form" id="demo-form">
    <input type="hidden" name="lang" value="{{ app()->getLocale() }}">
    <input type="hidden" name="ts" value="">
    <div class="hp" aria-hidden="true"><label for="f-website">Website</label><input id="f-website" name="website" tabindex="-1" autocomplete="off"></div>
    @include('partials.field', ['name' => 'ad', 'label' => __('ui.name'), 'attrs' => 'autocomplete="name" required maxlength="120"'])
    @include('partials.field', ['name' => 'email', 'type' => 'email', 'label' => __('ui.email'), 'attrs' => 'autocomplete="email" required'])
    <label for="f-kurs">{{ __('demo.course') }}</label>
    <select id="f-kurs" name="kurs" required>@foreach($courses as $c)<option value="{{ $c->slug }}" @selected(old('kurs', $selected) === $c->slug)>{{ $c->title() }}</option>@endforeach</select>
    @include('partials.field', ['name' => 'tel', 'type' => 'tel', 'label' => __('ui.phone'), 'attrs' => 'autocomplete="tel" inputmode="tel" required placeholder="'.e(__('ui.phone_placeholder')).'"'])
    <label class="check"><input type="checkbox" name="consent" value="1" required> <span>{{ __('demo.consent') }} <a href="{{ route('legal', 'privacy') }}">↗</a></span></label>
    @error('consent')<p class="err">{{ $message }}</p>@enderror
    <p class="small muted">{{ __('demo.once_note') }}</p>
    <div class="btns"><button class="btn" type="submit">{{ __('demo.submit') }}</button></div>
    <noscript><p class="err">{{ __('demo.js_required') }}</p></noscript>
  </form>
  <p class="small"><a href="{{ route('demo.resend.form') }}">{{ __('demo.resend_title') }}</a></p>
</div>
@endsection
@push('scripts')<script src="{{ passet('js/demo-form.js') }}" defer></script>@endpush
