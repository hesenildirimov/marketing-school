@extends('admin.layout')
@section('title', __('assess.grade_btn'))
@section('admin')
<p><a href="{{ route('admin.grading.index') }}">← {{ __('assess.queue') }}</a></p>
<h1>{{ $s->assignment->label() }}</h1>
<p class="muted">{{ $s->user->name }} · {{ $s->user->email }} · {{ __('assess.submitted_at') }}: {{ \App\Support\Tz::fmt($s->submitted_at) }}</p>
<section class="card"><h2>{{ __('assess.your_answer') }}</h2><div class="prose answer">{!! $s->body_html !!}</div>
<h3>{{ __('assess.files') }}</h3><ul class="plain">@forelse($s->files as $f)<li>
@if($f->isDownloadable())<a href="{{ \App\Http\Controllers\FileController::link($f) }}">{{ $f->original_name }}</a>@else<span>{{ $f->original_name }}</span>@endif
<span class="badge {{ $f->scan_status === 'clean' ? '' : 'badge-dark' }}">{{ __('assess.file_status.'.$f->scan_status) }}</span> <small class="muted">{{ number_format($f->size_bytes / 1024, 0) }} KB</small></li>
@empty<li class="muted">—</li>@endforelse</ul></section>
<section class="card"><h2>{{ __('assess.grade_btn') }}</h2>
<form method="POST" action="{{ route('admin.grading.grade', $s) }}" class="form">@csrf
  <label for="f-points">{{ __('assess.score') }} (0–{{ $max }})</label><input id="f-points" type="number" name="points" min="0" max="{{ $max }}" required value="{{ old('points', $s->currentGrade?->points) }}">
  @error('points')<p class="err">{{ $message }}</p>@enderror
  <label for="f-feedback">{{ __('assess.feedback') }}</label><textarea id="f-feedback" name="feedback" rows="5">{{ old('feedback', $s->currentGrade?->feedback) }}</textarea>
  <fieldset><legend>{{ __('assess.grade_status') }}</legend>
    <label class="check"><input type="radio" name="status" value="graded" checked> {{ __('assess.status.graded') }}</label>
    <label class="check"><input type="radio" name="status" value="needs_revision"> {{ __('assess.status.needs_revision') }}</label></fieldset>
  <div class="btns"><button class="btn">{{ __('ui.save') }}</button></div></form></section>
<section class="card"><h2>{{ __('assess.history') }}</h2><table><tbody>
@foreach($history as $g)<tr><td>{{ \App\Support\Tz::fmt($g->created_at) }}</td><td>{{ $g->points }} / {{ $g->max_points_snapshot }}</td><td>{{ \App\Models\User::find($g->graded_by)?->name }}</td><td>{{ $g->superseded_at ? '↺' : '●' }}</td></tr>@endforeach
@foreach($audit as $l)<tr class="small muted"><td>{{ \App\Support\Tz::fmt($l->created_at) }}</td><td colspan="3">{{ $l->action }} {{ json_encode($l->before) }} → {{ json_encode($l->after) }}</td></tr>@endforeach
</tbody></table></section>
@endsection
