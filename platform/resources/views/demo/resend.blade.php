@extends('layouts.app')
@section('title', __('demo.resend_title'))
@section('content')
<div class="auth-box card">
  <h1>{{ __('demo.resend_title') }}</h1>
  <p class="muted">{{ __('demo.resend_lede') }}</p>
  <form method="POST" action="{{ route('demo.resend') }}" class="form">@csrf
    @include('partials.field', ['name' => 'email', 'type' => 'email', 'label' => __('ui.email'), 'attrs' => 'autocomplete="email" required'])
    <div class="btns"><button class="btn" type="submit">{{ __('demo.resend_btn') }}</button></div>
  </form>
</div>
@endsection
