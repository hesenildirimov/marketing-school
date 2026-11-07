@extends('admin.layout')
@section('title', __('admin.students'))
@section('admin')
<div class="page-head"><h1>{{ __('admin.students') }}</h1>
<form method="GET" class="inline-form"><label class="sr-only" for="q">{{ __('admin.search') }}</label><input id="q" type="text" name="q" value="{{ request('q') }}" placeholder="{{ __('admin.search') }}"><button class="btn btn-sm">🔍</button></form></div>
<div class="card table-scroll"><table><thead><tr><th>{{ __('ui.name') }}</th><th>{{ __('ui.email') }}</th><th>{{ __('admin.registered') }}</th><th></th></tr></thead><tbody>
@forelse($users as $u)<tr><td>{{ $u->name }}</td><td>{{ $u->email }}</td><td>{{ \App\Support\Tz::fmt($u->created_at, 'd.m.Y') }}</td><td><a class="btn btn-sm btn-ghost" href="{{ route('admin.students.show', $u) }}">→</a></td></tr>
@empty<tr><td colspan="4">{{ __('ui.none') }}</td></tr>@endforelse</tbody></table></div>{{ $users->links() }}
@endsection
