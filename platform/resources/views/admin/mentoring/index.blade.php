@extends('admin.layout')
@section('title', __('mentoring.slots'))
@section('admin')
<h1>{{ __('mentoring.slots') }}</h1>
<section class="card"><h2>{{ __('mentoring.add_slots') }}</h2><form method="POST" action="{{ route('admin.mentoring.store') }}" class="form">@csrf
  <label for="mc">{{ __('demo.course') }}</label><select id="mc" name="course_id">@foreach($courses as $c)<option value="{{ $c->id }}">{{ $c->title() }}</option>@endforeach</select>
  <label for="md">{{ __('live.date') }}</label><input id="md" type="date" name="date" required>
  <label for="mt">{{ __('live.time') }}</label><input id="mt" type="text" name="time" pattern="[0-2][0-9]:[0-5][0-9]" placeholder="15:00" required>
  <label for="mdu">{{ __('live.duration') }}</label><input id="mdu" type="number" name="duration" value="45" min="15" max="180">
  <label for="mn">{{ __('mentoring.count') }}</label><input id="mn" type="number" name="count" value="1" min="1" max="12">
  <label for="mu">{{ __('live.meeting_url') }}</label><input id="mu" type="url" name="meeting_url" placeholder="https://meet.google.com/...">
  <div class="btns"><button class="btn btn-sm">{{ __('mentoring.add_slots') }}</button></div></form></section>
<div class="card table-scroll"><table><thead><tr><th>{{ __('live.when') }}</th><th>{{ __('demo.course') }}</th><th>{{ __('mentoring.student') }}</th><th></th></tr></thead><tbody>
@forelse($slots as $sl)@php($b = $bookings[$sl->id] ?? null)<tr><td>{{ \App\Support\Tz::fmt($sl->starts_at, 'D d.m H:i') }}–{{ \App\Support\Tz::fmt($sl->ends_at, 'H:i') }}</td>
<td>{{ \App\Models\Course::find($sl->course_id)->title() }}</td><td>{{ $b ? $b->user->name.' ('.$b->status.')' : __('mentoring.free') }}</td>
<td class="btns tight">@if($b && $b->status === 'booked')@foreach(['completed' => 'mark_completed', 'no_show' => 'mark_no_show', 'cancel' => 'cancel'] as $st => $lbl)<form method="POST" action="{{ route('admin.mentoring.mark', $b) }}">@csrf<input type="hidden" name="status" value="{{ $st }}"><button class="btn btn-sm btn-ghost">{{ __('mentoring.'.$lbl) }}</button></form>@endforeach
@elseif(! $b)<form method="POST" action="{{ route('admin.mentoring.destroy', $sl) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-ghost">✕</button></form>@endif</td></tr>
@empty<tr><td colspan="4">{{ __('ui.none') }}</td></tr>@endforelse</tbody></table></div>
@endsection
