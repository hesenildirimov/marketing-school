@extends('layouts.app')
@section('title', '404')
@section('content')
<div class="auth-box card"><h1>404</h1><p>{{ __('ui.error_404') }}</p><div class="btns"><a class="btn btn-ghost" href="{{ url('/platform') }}">{{ __('ui.home') }}</a></div></div>
@endsection
