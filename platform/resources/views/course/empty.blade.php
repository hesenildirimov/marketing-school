@extends('layouts.app')
@section('title', $course->title())
@section('content')
<h1>{{ $course->title() }}</h1><p class="muted">{{ __('course.empty') }}</p>
@endsection
