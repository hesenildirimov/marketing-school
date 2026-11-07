@extends('admin.layout')
@section('title', __('admin.courses'))
@section('admin')
<h1>{{ __('admin.courses') }}</h1>
<div class="grid">@foreach($courses as $c)<article class="card"><h2><a href="{{ route('admin.courses.show', $c) }}">{{ $c->title() }}</a></h2>
<p class="small muted">/{{ $c->slug }} · {{ __('admin.current_version') }}: v{{ $c->currentVersion?->version_no ?? '—' }} @if($c->draft()) · <span class="badge">{{ __('admin.draft') }}</span>@endif</p></article>@endforeach</div>
@endsection
