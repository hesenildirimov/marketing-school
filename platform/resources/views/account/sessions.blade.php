@extends('layouts.app')
@section('title', __('ui.sessions_title'))
@section('content')
@include('account.nav')
<h1>{{ __('ui.sessions_title') }}</h1>
<p class="muted">{{ __('ui.sessions_lede', ['limit' => app(\App\Domain\Auth\SessionManager::class)->limit()]) }}</p>
<div class="card table-scroll"><table>
  <thead><tr><th>Cihaz</th><th>{{ __('ui.session_last_seen') }}</th><th></th></tr></thead>
  <tbody>@foreach($sessions as $s)<tr>
    <td>{{ \Illuminate\Support\Str::limit($s->user_agent, 70) }} @if($current && $s->id === $current->id)<span class="badge">{{ __('ui.session_current') }}</span>@endif</td>
    <td>{{ \App\Support\Tz::fmt($s->last_seen_at) }}</td>
    <td>@if(! $current || $s->id !== $current->id)<form method="POST" action="{{ route('account.sessions.revoke', $s->id) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-ghost">{{ __('ui.session_revoke') }}</button></form>@endif</td>
  </tr>@endforeach</tbody>
</table></div>
@endsection
