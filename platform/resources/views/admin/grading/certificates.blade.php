@extends('admin.layout')
@section('title', __('assess.certificates'))
@section('admin')
<h1>{{ __('assess.certificates') }}</h1>
<div class="card table-scroll"><table><thead><tr><th>{{ __('assess.holder') }}</th><th>{{ __('assess.course') }}</th><th>%</th><th>{{ __('assess.issued') }}</th><th>ID</th><th></th></tr></thead><tbody>
@forelse($certs as $c)<tr><td>{{ $c->snapshot['name'] }}</td><td>{{ tr_field($c->snapshot['course']) }}</td><td>{{ $c->snapshot['percent'] }}</td><td>{{ $c->snapshot['date'] }}</td>
<td><a href="{{ route('certificates.verify', $c->verify_id) }}"><code>{{ $c->verify_id }}</code></a></td>
<td>@if($c->revoked_at)<span class="badge badge-dark">{{ __('assess.revoked') }}</span>@else
<details><summary class="small">{{ __('assess.revoke') }}</summary><form method="POST" action="{{ route('admin.certificates.revoke', $c) }}">@csrf<label for="r-{{ $c->id }}" class="small">{{ __('admin.reason') }}</label><textarea id="r-{{ $c->id }}" name="reason" required minlength="5" rows="2"></textarea><button class="btn btn-sm btn-danger">{{ __('assess.revoke') }}</button></form></details>@endif</td></tr>
@empty<tr><td colspan="6">{{ __('ui.none') }}</td></tr>@endforelse</tbody></table></div>{{ $certs->links() }}
@endsection
