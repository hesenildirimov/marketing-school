@extends('admin.layout')
@section('title', __('assess.queue'))
@section('admin')
<div class="page-head"><h1>{{ __('assess.queue') }}</h1>
<nav class="btns tight">@foreach(['submitted', 'reviewing', 'needs_revision', 'graded'] as $st)<a class="btn btn-sm {{ $status === $st ? '' : 'btn-ghost' }}" href="?status={{ $st }}">{{ __('assess.status.'.$st) }}</a>@endforeach
@if(auth()->user()->isAdmin())<a class="btn btn-sm btn-ghost" href="{{ route('admin.certificates.index') }}">{{ __('assess.certificates') }}</a>@endif</nav></div>
<div class="card table-scroll"><table><thead><tr><th>{{ __('assess.student') }}</th><th>{{ __('assess.assignment') }}</th><th>{{ __('assess.submitted_at') }}</th><th></th></tr></thead>
<tbody>@forelse($subs as $s)<tr><td>{{ $s->user->name }}<br><small class="muted">{{ $s->user->email }}</small></td>
<td>{{ $s->assignment->label() }}<br><small class="muted">{{ $s->assignment->course->title() }}</small></td><td>{{ \App\Support\Tz::fmt($s->submitted_at) }}</td>
<td><a class="btn btn-sm" href="{{ route('admin.grading.show', $s) }}">{{ __('assess.grade_btn') }}</a></td></tr>
@empty<tr><td colspan="4">{{ __('ui.none') }}</td></tr>@endforelse</tbody></table></div>
{{ $subs->links() }}
@endsection
