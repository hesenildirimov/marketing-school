@extends('layouts.app')
@section('title', __('ui.check_email_title'))
@section('content')
<div class="auth-box card">
  <h1>{{ __('ui.check_email_title') }}</h1>
  <p>{{ __('ui.check_email_text') }}</p>
  @if(session('demo_note'))<p class="alert">{{ session('demo_note') }}</p>@endif
</div>
@endsection
