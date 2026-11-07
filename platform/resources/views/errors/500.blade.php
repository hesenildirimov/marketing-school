@extends('layouts.app')
@section('title', '500')
@section('content')
<div class="auth-box card"><h1>500</h1><p>{{ __('ui.error_500') }}</p><div class="btns"><a class="btn btn-ghost" href="{{ url('/platform') }}">{{ __('ui.home') }}</a></div></div>
@endsection
