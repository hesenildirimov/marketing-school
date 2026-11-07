@extends('layouts.app')
@section('title', __('mentoring.title'))
@section('content')
@include('course.subnav', ['course' => $course])
<h1>{{ __('mentoring.title') }}</h1>
@php($hours = \App\Models\BillingRule::for($course->id)->mentoring_cancel_hours)
@if(! $unlocked)
  <div class="card locked-card"><p>🔒 {{ __('mentoring.locked') }}</p><a class="btn" href="{{ route('course.show', $course) }}">{{ __('course.continue') }}</a></div>
@else
  @if($booking)
  <section class="card"><h2>{{ __('mentoring.your_booking') }}</h2>
    <p><b>{{ \App\Support\Tz::fmt($booking->slot->starts_at) }}</b>–{{ \App\Support\Tz::fmt($booking->slot->ends_at, 'H:i') }} ({{ $booking->slot->instructor?->name }})</p>
    @if($booking->status === 'booked')
      @if($booking->slot->meeting_url_encrypted)<p><a class="btn btn-sm" href="{{ $booking->slot->meeting_url_encrypted }}" rel="noopener noreferrer" target="_blank">{{ __('mentoring.join') }}</a></p>@endif
      <p class="small muted">{{ __('mentoring.rule', ['h' => $hours]) }}</p>
      <form method="POST" action="{{ route('mentoring.cancel', $booking) }}">@csrf<button class="btn btn-sm btn-ghost">{{ __('mentoring.cancel') }}</button></form>
      @error('booking')<p class="err">{{ $message }}</p>@enderror
    @elseif($booking->status === 'completed')<p class="alert">{{ __('mentoring.completed') }}</p>
    @else<p class="alert">{{ __('mentoring.no_show') }}</p>@endif
  </section>
  @endif
  @if(! $booking || $booking->status === 'booked')
  <section class="card"><h2>{{ $booking ? __('mentoring.reschedule') : __('mentoring.book') }}</h2><p class="muted small">{{ __('mentoring.pick') }} {{ __('mentoring.rule', ['h' => $hours]) }}</p>
    @error('slot')<p class="err">{{ $message }}</p>@enderror
    <div class="slot-grid">@forelse($slots as $sl)
      <form method="POST" action="{{ $booking ? route('mentoring.reschedule', $booking) : route('mentoring.book', $course) }}">@csrf<input type="hidden" name="slot_id" value="{{ $sl->id }}">
        <button class="btn btn-ghost btn-sm">{{ \App\Support\Tz::fmt($sl->starts_at, 'D d.m') }} · {{ \App\Support\Tz::fmt($sl->starts_at, 'H:i') }}</button></form>
    @empty<p class="muted">{{ __('mentoring.no_slots') }}</p>@endforelse</div>
  </section>
  @endif
@endif
@endsection
