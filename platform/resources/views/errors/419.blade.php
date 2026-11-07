@extends('layouts.app')
@section('title', '419')
@section('content')
<div class="auth-box card"><h1>419</h1><p>{{ __('ui.error_419') }}</p><div class="btns"><a class="btn btn-ghost" href="{{ url('/platform') }}">{{ __('ui.home') }}</a></div></div>
@endsection
