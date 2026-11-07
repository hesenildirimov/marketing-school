@extends('admin.layout')
@section('title', __('admin.nav.billing'))
@section('admin')
<h1>{{ __('admin.nav.billing') }}</h1>
<section class="card"><h2>{{ __('billing.manual') }}</h2>
<form method="POST" action="{{ route('admin.billing.activate') }}" class="form">@csrf
  <label for="ba-e">{{ __('ui.email') }}</label><input id="ba-e" type="email" name="email" required value="{{ old('email') }}">
  <label for="ba-c">{{ __('billing.course') }}</label><select id="ba-c" name="course_id">@foreach($courses as $c)<option value="{{ $c->id }}">{{ $c->title() }}</option>@endforeach</select>
  <label for="ba-m">{{ __('billing.months') }}</label><input id="ba-m" type="number" name="months" min="1" max="24" value="1">
  <label for="ba-r">{{ __('admin.reason') }}</label><textarea id="ba-r" name="reason" required minlength="5" rows="2"></textarea>
  @foreach($errors->all() as $e)<p class="err">{{ $e }}</p>@endforeach
  <div class="btns"><button class="btn btn-sm">{{ __('billing.activate') }}</button></div></form></section>

<section class="card"><h2>{{ __('billing.orders') }}</h2><div class="table-scroll"><table>
<thead><tr><th>{{ __('billing.date') }}</th><th>{{ __('billing.student') }}</th><th>{{ __('billing.course') }}</th><th>{{ __('billing.amount') }}</th><th>{{ __('billing.status') }}</th><th></th></tr></thead><tbody>
@forelse($orders as $o)<tr><td>{{ \App\Support\Tz::fmt($o->created_at) }}</td><td>{{ $o->user->email }}</td><td>{{ $o->course->title() }}</td><td>{{ number_format($o->amount_qepik / 100, 2) }} {{ $o->currency }}</td>
<td><span class="badge {{ $o->status === 'paid' ? '' : 'badge-muted' }}">{{ $o->status }}</span> <small class="muted">{{ $o->purpose }}</small></td>
<td>@if($o->status === 'paid')<details><summary class="small">{{ __('billing.refund') }}</summary><form method="POST" action="{{ route('admin.billing.refund', $o) }}">@csrf<textarea name="reason" required minlength="5" rows="2" aria-label="{{ __('admin.reason') }}"></textarea><button class="btn btn-sm btn-danger">{{ __('billing.refund') }}</button></form></details>@endif</td></tr>
@empty<tr><td colspan="6">{{ __('ui.none') }}</td></tr>@endforelse</tbody></table></div></section>

<section class="card"><h2>{{ __('billing.webhooks') }}</h2><table><tbody>
@forelse($events as $ev)<tr><td>{{ \App\Support\Tz::fmt($ev->received_at) }}</td><td>{{ $ev->provider }}</td><td><code>{{ \Illuminate\Support\Str::limit($ev->provider_event_id, 30) }}</code></td><td>{{ $ev->status }} ({{ $ev->attempts }})</td><td class="small muted">{{ \Illuminate\Support\Str::limit($ev->last_error, 80) }}</td>
<td><form method="POST" action="{{ route('admin.billing.retry', $ev) }}">@csrf<button class="btn btn-sm btn-ghost">{{ __('billing.retry_btn') }}</button></form></td></tr>
@empty<tr><td>{{ __('ui.none') }}</td></tr>@endforelse</tbody></table></section>

<section class="card"><h2>{{ __('billing.rules') }}</h2>
<form method="POST" action="{{ route('admin.billing.rules') }}" class="form">@csrf @method('PUT')
  @foreach(['grace_period_days' => 'grace', 'min_commitment_months' => 'min_commit', 'session_limit' => 'session_limit', 'mentoring_cancel_hours' => 'mentor_cancel', 'upload_file_mb' => 'upload_mb', 'upload_quota_mb' => 'quota_mb', 'upload_files_per_submission' => 'files_n'] as $f => $l)
  <label for="r-{{ $f }}">{{ __('billing.'.$l) }}</label><input id="r-{{ $f }}" type="number" name="{{ $f }}" value="{{ $rules->$f }}">@endforeach
  <label class="check"><input type="checkbox" name="auto_stop_on_completion" value="1" @checked($rules->auto_stop_on_completion)> {{ __('billing.auto_stop') }}</label>
  <label for="r-reason">{{ __('admin.reason') }}</label><textarea id="r-reason" name="reason" required minlength="5" rows="2"></textarea>
  <div class="btns"><button class="btn btn-sm">{{ __('ui.save') }}</button></div></form></section>

<section class="card"><h2>{{ __('billing.prices') }}</h2>
@foreach($courses as $c)<p><b>{{ $c->title() }}</b>: @foreach($prices[$c->id] ?? [] as $p){{ $p->display() }} ({{ \App\Support\Tz::fmt($p->valid_from, 'd.m.Y') }})@if(! $loop->last), @endif @endforeach</p>@endforeach
<form method="POST" action="{{ route('admin.billing.price') }}" class="form">@csrf
  <label for="p-c">{{ __('billing.course') }}</label><select id="p-c" name="course_id">@foreach($courses as $c)<option value="{{ $c->id }}">{{ $c->title() }}</option>@endforeach</select>
  <label for="p-a">{{ __('billing.new_price') }}</label><input id="p-a" type="number" name="amount" step="0.01" min="1">
  <label for="p-v">{{ __('billing.valid_from') }}</label><input id="p-v" type="date" name="valid_from">
  <div class="btns"><button class="btn btn-sm">{{ __('ui.save') }}</button></div></form></section>
@endsection
