@extends('admin.layout')
@section('title', __('ui.admin_title'))
@section('admin')
<h1>{{ __('ui.admin_title') }}</h1>
<p class="muted">{{ __('admin.home_lede') }}</p>
@php
  $acc = app(\App\Domain\Access\AccessPolicy::class);
  $courseIds = \App\Models\Course::all()->filter(fn ($c) => $acc->isStaffFor(auth()->user(), $c))->pluck('id');
  $stats = ['pending_grading' => \App\Models\Submission::whereIn('course_id', $courseIds)->whereIn('status', ['submitted', 'reviewing'])->count()];
  if (auth()->user()->isAdmin()) {
      $stats['failed_emails'] = \App\Models\AppNotification::where('channel', 'email')->where('status', 'failed')->count();
      $stats['failed_webhooks'] = \App\Models\PaymentWebhookEvent::where('status', 'failed')->count();
      $grace = \App\Models\BillingRule::for(null)->grace_period_days;
      $stats['active_students'] = \App\Models\Enrollment::whereNull('canceled_at')->whereRaw('paid_until + make_interval(days => ?) >= now()', [$grace])->count();
  }
@endphp
<div class="stat-grid">@foreach($stats as $k => $v)<div class="stat card"><b>{{ $v }}</b><span class="small muted">{{ __('admin.'.$k) }}</span></div>@endforeach</div>
@endsection
