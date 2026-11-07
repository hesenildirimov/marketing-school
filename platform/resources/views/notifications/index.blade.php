@extends('layouts.app')
@section('title', __('privacy.notifications'))
@section('content')
<h1>{{ __('privacy.notifications') }}</h1>
<div class="card">@forelse($items as $n)
  <div class="notif {{ $n->read_at ? '' : 'unread' }}"><b>{{ __('mail.'.$n->event_type.'.subject', array_filter($n->payload ?? [], 'is_scalar')) }}</b>
    <span class="small muted">{{ \App\Support\Tz::fmt($n->scheduled_for) }}</span>
    @if(! empty($n->payload['url']) && str_starts_with($n->payload['url'], url('/platform')))<a class="small" href="{{ $n->payload['url'] }}">→</a>@endif</div>
@empty<p class="muted">{{ __('privacy.no_notifications') }}</p>@endforelse</div>
{{ $items->links() }}
@endsection
