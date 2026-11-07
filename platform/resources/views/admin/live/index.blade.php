@extends('admin.layout')
@section('title', __('live.title'))
@section('admin')
<h1>{{ __('live.title') }}</h1>
@foreach($courses as $c)
<section class="card"><div class="c-row"><h2>{{ $c->title() }}</h2>
  <form method="POST" action="{{ route('admin.live.generate', $c) }}">@csrf<button class="btn btn-sm btn-ghost">{{ __('live.generate') }}</button></form></div>
  <table><thead><tr><th>{{ __('live.when') }}</th><th>{{ __('live.status') }}</th><th>{{ __('live.meeting_url') }}</th><th></th></tr></thead><tbody>
  @forelse($sessions[$c->id] ?? [] as $s)<tr><td>{{ \App\Support\Tz::fmt($s->starts_at, 'D d.m.Y H:i') }}</td><td>{{ $s->status }}{{ $s->source === 'manual' ? ' ✎' : '' }}</td>
    <td>{{ $s->meeting_url_encrypted ? '✓' : '—' }}</td><td><a class="btn btn-sm btn-ghost" href="{{ route('admin.live.edit', $s) }}">{{ __('admin.edit') }}</a></td></tr>
  @empty<tr><td colspan="4">{{ __('ui.none') }}</td></tr>@endforelse</tbody></table></section>
@endforeach
@endsection
