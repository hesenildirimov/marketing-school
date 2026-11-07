@extends('admin.layout')
@section('title', __('admin.audit_title'))
@section('admin')
<div class="page-head"><h1>{{ __('admin.audit_title') }}</h1>
<form method="GET" class="inline-form"><input type="text" name="action" value="{{ request('action') }}" placeholder="billing." aria-label="{{ __('admin.action') }}"><button class="btn btn-sm">🔍</button></form></div>
@if($chainBroken)<p class="alert alert-strong">{{ __('admin.chain_broken', ['id' => $chainBroken]) }}</p>@else<p class="alert small">✓ {{ __('admin.chain_ok') }}</p>@endif
<div class="card table-scroll"><table><thead><tr><th>#</th><th>{{ __('billing.date') }}</th><th>{{ __('admin.actor') }}</th><th>{{ __('admin.action') }}</th><th>{{ __('admin.entity') }}</th><th>{{ __('admin.reason') }}</th></tr></thead><tbody>
@foreach($logs as $l)<tr><td>{{ $l->id }}</td><td>{{ \App\Support\Tz::fmt($l->created_at) }}</td><td>{{ \App\Models\User::find($l->actor_user_id)?->email ?? 'system' }}</td><td><code>{{ $l->action }}</code></td>
<td>{{ $l->entity_type }}{{ $l->entity_id ? ' #'.$l->entity_id : '' }}<br><small class="muted">{{ \Illuminate\Support\Str::limit(json_encode($l->after, JSON_UNESCAPED_UNICODE), 90) }}</small></td><td>{{ $l->reason }}</td></tr>@endforeach
</tbody></table></div>{{ $logs->links() }}
@endsection
