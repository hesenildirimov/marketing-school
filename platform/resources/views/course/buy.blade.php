@extends('layouts.app')
@section('title', __('course.buy_title'))
@section('content')
<div class="auth-box card">
  <p class="eyebrow-s">{{ $course->title() }}</p>
  <h1>{{ __('course.buy_title') }}</h1>
  @if($demoExpired && ! $enrollment)<p class="alert">{{ __('course.buy_demo_expired') }}</p>@endif
  @if($enrollment && in_array($status, ['expired', 'canceled']))<p class="alert">{{ __('course.buy_expired') }}</p>@endif
  <p>{{ __('course.buy_lede') }}</p>
  <p class="small muted">{{ __('course.video_lang_note') }}</p>
  <div class="btns">
    @if(Route::has('billing.checkout'))<form method="POST" action="{{ route('billing.checkout', $course) }}">@csrf<button class="btn">{{ __('course.buy', ['price' => price_label($course)]) }}</button></form>
    @else<p>{{ __('course.buy_contact') }}</p>@endif
    <a class="btn btn-ghost" href="{{ route('dashboard') }}">{{ __('ui.back') }}</a>
  </div>
</div>
@endsection
