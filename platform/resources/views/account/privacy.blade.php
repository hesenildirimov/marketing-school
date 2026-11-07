@extends('layouts.app')
@section('title', __('privacy.title'))
@section('content')
@include('account.nav')
<h1>{{ __('privacy.title') }}</h1>
<p class="muted">{{ __('privacy.lede') }}</p>
<section class="card"><a class="btn btn-ghost" href="{{ route('account.export') }}">{{ __('privacy.export') }}</a>
  <p class="small"><a href="{{ route('legal', 'privacy') }}">{{ __('legal.privacy.title') }}</a> · <a href="{{ route('legal', 'terms') }}">{{ __('legal.terms.title') }}</a></p></section>
@if(! auth()->user()->isStaff())
<section class="card"><h2>{{ __('privacy.delete_title') }}</h2><p>{{ __('privacy.delete_lede') }}</p>
  <form method="POST" action="{{ route('account.destroy') }}" class="form">@csrf @method('DELETE')
    <label for="conf">{{ __('privacy.confirm_label') }}</label><input id="conf" type="text" name="confirm" required autocomplete="off">
    @error('confirm')<p class="err">{{ $message }}</p>@enderror
    <div class="btns"><button class="btn btn-danger">{{ __('privacy.delete_btn') }}</button></div></form></section>
@endif
@endsection
