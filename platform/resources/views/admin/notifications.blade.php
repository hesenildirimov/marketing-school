@extends('admin.layout')
@section('title', __('admin.notif_title'))
@section('admin')
<div class="page-head"><h1>{{ __('admin.notif_title') }}</h1>
<nav class="btns tight">@foreach(['failed', 'pending', 'sent', 'canceled'] as $st)<a class="btn btn-sm {{ $status === $st ? '' : 'btn-ghost' }}" href="?status={{ $st }}">{{ $st }} ({{ $counts[$st] ?? 0 }})</a>@endforeach</nav></div>
<div class="card table-scroll"><table><thead><tr><th>{{ __('admin.event') }}</th><th>{{ __('ui.email') }}</th><th>{{ __('admin.attempts') }}</th><th>{{ __('admin.error') }}</th><th></th></tr></thead><tbody>
@forelse($items as $n)<tr><td>{{ $n->event_type }}<br><small class="muted">{{ \App\Support\Tz::fmt($n->scheduled_for) }} · P{{ $n->priority }}</small></td><td>{{ $n->user?->email }}</td><td>{{ $n->attempts }}</td>
<td class="small muted">{{ \Illuminate\Support\Str::limit($n->last_error, 80) }}</td>
<td>@if(in_array($n->status, ['failed', 'sent']))<form method="POST" action="{{ route('admin.notifications.resend', $n) }}">@csrf<button class="btn btn-sm btn-ghost">{{ __('admin.resend') }}</button></form>@endif</td></tr>
@empty<tr><td colspan="5">{{ __('ui.none') }}</td></tr>@endforelse</tbody></table></div>{{ $items->links() }}
@endsection
