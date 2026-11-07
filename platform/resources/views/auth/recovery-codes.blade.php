@extends('layouts.app')
@section('title', __('ui.recovery_title'))
@section('content')
<div class="auth-box card">
  <h1>{{ __('ui.recovery_title') }}</h1>
  <p class="muted">{{ __('ui.recovery_lede') }}</p>
  <ul class="codes">@foreach($codes as $c)<li>{{ $c }}</li>@endforeach</ul>
  <div class="btns"><a class="btn" href="{{ route('admin.home') }}">{{ __('ui.continue') }}</a></div>
</div>
@endsection
