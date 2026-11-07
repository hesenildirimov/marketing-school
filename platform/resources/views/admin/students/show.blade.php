@extends('admin.layout')
@section('title', $u->name)
@section('admin')
<p><a href="{{ route('admin.students.index') }}">← {{ __('admin.students') }}</a></p>
<h1>{{ $u->name }}</h1>
<p class="muted">{{ $u->email }} · {{ $u->phone_e164 }} · {{ strtoupper($u->locale) }} · {{ implode(', ', $u->roles()) }}</p>
@if($demo)<p class="alert small">Demo: {{ \App\Models\Course::find($demo->course_id)?->title() }} · {{ \App\Support\Tz::fmt(\Carbon\Carbon::parse($demo->started_at)) }} → {{ \App\Support\Tz::fmt(\Carbon\Carbon::parse($demo->expires_at)) }} {{ $demo->converted_at ? '✓ '.__('admin.demo_converted') : '' }}</p>@endif
<section class="card"><h2>{{ __('admin.enrollments') }}</h2><div class="table-scroll"><table>
<thead><tr><th>{{ __('demo.course') }}</th><th>Status</th><th>{{ __('admin.paid_until') }}</th><th>{{ __('admin.grace_until') }}</th><th>{{ __('admin.progress') }}</th><th>{{ __('admin.subscription') }}</th><th></th></tr></thead><tbody>
@forelse($rows as $r)<tr><td>{{ $r['e']->course->title() }} <small class="muted">v{{ \App\Models\CourseVersion::find($r['e']->course_version_id)?->version_no }}</small></td>
<td><span class="badge">{{ __('course.status.'.$r['status']) }}</span></td><td>{{ \App\Support\Tz::fmt($r['e']->paid_until) }}</td><td>{{ \App\Support\Tz::fmt($r['grace_until']) }}</td>
<td>{{ $r['progress']['percent'] }}% ({{ $r['progress']['completed'] }}/{{ $r['progress']['total'] }}) @if($r['e']->mentoring_unlocked_at) · 1:1 ✓@endif</td>
<td>{{ $r['sub'] ? $r['sub']->status.($r['sub']->cancel_at_period_end ? ' · cancel_at_period_end' : '') : '—' }}</td>
<td>@if(! $r['e']->canceled_at)<details><summary class="small">{{ __('admin.cancel_enrollment') }}</summary><form method="POST" action="{{ route('admin.enrollments.cancel', $r['e']) }}">@csrf<textarea name="reason" required minlength="5" rows="2" aria-label="{{ __('admin.reason') }}"></textarea><button class="btn btn-sm btn-danger">{{ __('admin.cancel_enrollment') }}</button></form></details>@endif</td></tr>
@empty<tr><td colspan="7">{{ __('ui.none') }}</td></tr>@endforelse</tbody></table></div></section>
<section class="card"><h2>{{ __('admin.sessions') }} ({{ $sessions->count() }})</h2>
<form method="POST" action="{{ route('admin.students.revoke', $u) }}">@csrf<button class="btn btn-sm btn-ghost">{{ __('admin.revoke_sessions') }}</button></form></section>
@endsection
