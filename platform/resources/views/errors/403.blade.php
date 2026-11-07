@extends('layouts.app')
@section('title', '403')
@section('content')
<div class="auth-box card"><h1>403</h1><p>{{ __('ui.error_403') }}</p><div class="btns"><a class="btn btn-ghost" href="{{ url('/platform') }}">{{ __('ui.home') }}</a></div></div>
@endsection
