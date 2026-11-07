@extends('admin.layout')
@section('title', __('admin.analytics_title'))
@section('admin')
<div class="page-head"><h1>{{ __('admin.analytics_title') }}</h1>
<nav class="btns tight" aria-label="{{ __('admin.period') }}">@foreach([7, 30, 90, 365] as $d)<a class="btn btn-sm {{ $days === $d ? '' : 'btn-ghost' }}" href="?days={{ $d }}">{{ $d }} {{ __('admin.days') }}</a>@endforeach</nav></div>
@foreach($data as $row)@php($m = $row['m'])
<section class="card"><h2>{{ $row['course']->title() }}</h2>
  <div class="stat-grid">@foreach(__('admin.m') as $k => $label)<div class="stat"><b>{{ $m[$k] ?? '—' }}</b><span class="small muted">{{ $label }}</span></div>@endforeach</div>
  <h3>{{ __('admin.statuses') }}</h3><p>@foreach($m['statuses'] as $s => $n)<span class="badge badge-muted">{{ __('course.status.'.$s) }}: {{ $n }}</span> @endforeach</p>
  <div class="grid">
    <div><h3>{{ __('admin.dropoff') }}</h3><table><tbody><tr><td>{{ __('admin.never_started') }}</td><td>{{ $m['demo_dropoff']['never_started'] ?? 0 }}</td></tr>
      @foreach($m['demo_dropoff']['by_lesson'] ?? [] as $d)<tr><td>{{ $d['lesson'] }}</td><td>{{ $d['users'] }}</td></tr>@endforeach</tbody></table></div>
    <div><h3>{{ __('admin.abandonment') }}</h3><table><tbody>@forelse($m['abandonment'] as $a)<tr><td>{{ $a['lesson'] }}</td><td>{{ $a['peak_minute'] }}′</td><td>{{ $a['viewers'] }}</td></tr>@empty<tr><td>{{ __('admin.no_data') }}</td></tr>@endforelse</tbody></table></div>
    <div><h3>{{ __('admin.revenue') }}</h3><table><tbody>@forelse($m['revenue'] as $r)<tr><td>{{ $r['month'] }}</td><td>{{ number_format($r['net_azn'], 2) }}</td><td>{{ $r['charges'] }}</td></tr>@empty<tr><td>{{ __('admin.no_data') }}</td></tr>@endforelse</tbody></table></div>
    <div><h3>{{ __('admin.player') }}</h3><table><thead><tr><th></th><th>n</th><th>TTFF ms</th><th>p75</th><th>rebuf</th><th>kbps</th></tr></thead><tbody>@forelse($m['player'] as $p)<tr><td>{{ $p['device'] }}</td><td>{{ $p['sessions'] }}</td><td>{{ $p['ttff_ms'] }}</td><td>{{ round((float) $p['ttff_p75']) }}</td><td>{{ $p['rebuffers'] }}</td><td>{{ $p['kbps'] }}</td></tr>@empty<tr><td colspan="6">{{ __('admin.no_data') }}</td></tr>@endforelse</tbody></table></div>
  </div>
</section>
@endforeach
@endsection
