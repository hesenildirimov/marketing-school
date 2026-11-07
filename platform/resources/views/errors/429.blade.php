@extends('layouts.app')
@section('title', '429')
@section('content')
<div class="auth-box card"><h1>429</h1><p>{{ __('ui.error_429') }}</p><div class="btns"><a class="btn btn-ghost" href="{{ url('/platform') }}">{{ __('ui.home') }}</a></div></div>
@endsection
