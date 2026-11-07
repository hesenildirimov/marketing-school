@extends('layouts.app')
@section('title', __("legal.$page.title"))
@section('content')
<article class="card">
  <h1>{{ __("legal.$page.title") }}</h1>
  <p class="alert">{{ __('legal.placeholder') }}</p>
  @foreach(__("legal.$page.body") as $p)<p>{{ $p }}</p>@endforeach
</article>
@endsection
