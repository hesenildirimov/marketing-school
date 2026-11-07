@extends('admin.layout')
@section('title', __('admin.nav.demos'))
@section('admin')
<div class="page-head"><h1>{{ __('admin.nav.demos') }}</h1>
<form method="GET" class="inline-form"><label class="sr-only" for="q">E-mail</label><input id="q" type="text" name="q" value="{{ request('q') }}" placeholder="e-mail"><button class="btn btn-sm">🔍</button></form></div>
<div class="card table-scroll"><table>
<thead><tr><th>E-mail</th><th>{{ __('demo.course') }}</th><th>{{ __('admin.demo_started') }}</th><th>{{ __('admin.demo_expires') }}</th><th>{{ __('admin.demo_confirmed') }}</th><th>{{ __('admin.demo_converted') }}</th><th></th></tr></thead>
<tbody>@forelse($claims as $c)<tr>
  <td>{{ $c->user->email }}</td><td>{{ $c->course->title() }}</td>
  <td>{{ \App\Support\Tz::fmt($c->started_at) }}</td><td>{{ \App\Support\Tz::fmt($c->expires_at) }} @if($c->isActive())<span class="badge">●</span>@endif</td>
  <td>{{ $c->email_confirmed_at ? '✓' : '—' }}</td><td>{{ $c->converted_at ? '✓ '.\App\Support\Tz::fmt($c->converted_at, 'd.m.Y') : ($c->converted_other_at ? '↔' : '—') }}</td>
  <td>@if(! $c->user->email_verified_at)<form method="POST" action="{{ route('admin.demos.resend', $c->user_id) }}">@csrf<button class="btn btn-sm btn-ghost">{{ __('admin.resend_link') }}</button></form>@endif</td>
</tr>@empty<tr><td colspan="7">{{ __('ui.none') }}</td></tr>@endforelse</tbody></table></div>
{{ $claims->links() }}
@endsection
