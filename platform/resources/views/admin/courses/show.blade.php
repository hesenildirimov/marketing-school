@extends('admin.layout')
@section('title', $course->title())
@section('admin')
<div class="page-head"><h1>{{ $course->title() }}</h1>
  <p class="small muted">{{ __('admin.current_version') }}: v{{ $course->currentVersion?->version_no ?? '—' }}</p></div>
<p class="small muted">{{ __('admin.draft_note') }}</p>
@php($m = $draftManifest ?? $current)
@if(! $draft)
  <form method="POST" action="{{ route('admin.courses.draft', $course) }}">@csrf<button class="btn">{{ __('admin.create_draft') }}</button></form>
@endif
@if($m)
@foreach($m->modules as $i => $mod)
  <section class="card">
    <div class="c-row"><h2>{{ __('course.module_n', ['n' => $i + 1]) }}. {{ tr_field($mod->title) }} @if($mod->demo_open)<span class="badge">{{ __('admin.demo_open') }}</span>@endif</h2>
    @if($draft)<div class="btns tight">@foreach(['up', 'down', $mod->demo_open ? 'demo_off' : 'demo_on'] as $a)<form method="POST" action="{{ route('admin.modules.action', [$course, $mod->id]) }}">@csrf<input type="hidden" name="action" value="{{ $a }}"><button class="btn btn-sm btn-ghost">{{ __('admin.'.$a) }}</button></form>@endforeach</div>@endif</div>
    <ol class="admin-lessons">@foreach($m->lessonsOf($mod->id) as $l)<li>
      <a href="{{ route('admin.lessons.edit', [$course, $l->id]) }}">{{ tr_field($l->title) }}</a>
      <span class="small muted">{{ $l->video_status ? __('admin.video').': '.$l->video_status : '' }}</span>
      @if($draft)<span class="btns tight">@foreach(['up', 'down', 'remove'] as $a)<form method="POST" action="{{ route('admin.lessons.action', [$course, $l->id]) }}">@csrf<input type="hidden" name="action" value="{{ $a }}"><button class="btn btn-sm btn-ghost">{{ __('admin.'.$a) }}</button></form>@endforeach</span>@endif
    </li>@endforeach</ol>
    @php($asg = app(\App\Domain\Assessment\AssignmentService::class)->forVersion(($draft ?? $course->currentVersion)->id, $mod->id))
    @if($asg->isNotEmpty())<p class="small"><b>{{ __('assess.assignments') }}:</b> @foreach($asg as $x)<a href="{{ route('admin.assignments.edit', [$course, $x->id]) }}">{{ tr_field($x->title) }}</a> ({{ $x->max_points }}{{ $x->is_mandatory ? ', '.__('assess.mandatory') : '' }})@if(! $loop->last) · @endif @endforeach</p>@endif
    @if($draft)<details><summary class="small">{{ __('assess.add_assignment') }}</summary>
      <form method="POST" action="{{ route('admin.assignments.store', [$course, $mod->id]) }}">@csrf
        @include('admin.partials.tri', ['name' => 'title', 'label' => __('admin.title'), 'value' => [], 'required' => true])
        @include('admin.partials.tri', ['name' => 'body', 'label' => __('assess.body'), 'value' => [], 'textarea' => true, 'required' => true])
        <label for="mp-{{ $mod->id }}">{{ __('assess.max_points') }}</label><input id="mp-{{ $mod->id }}" type="number" name="max_points" min="1" max="1000" value="10" required>
        <label class="check"><input type="checkbox" name="is_mandatory" value="1" checked> {{ __('assess.mandatory') }}</label>
        <button class="btn btn-sm">{{ __('assess.add_assignment') }}</button></form></details>@endif
    <details><summary class="small">{{ __('admin.edit') }}</summary>
      <form method="POST" action="{{ route('admin.modules.update', [$course, $mod->id]) }}">@csrf @method('PUT')
        @include('admin.partials.tri', ['name' => 'title', 'label' => __('admin.title'), 'value' => $mod->title, 'required' => true])
        @include('admin.partials.tri', ['name' => 'description', 'label' => __('admin.description'), 'value' => $mod->description ?? [], 'textarea' => true])
        <button class="btn btn-sm">{{ __('ui.save') }}</button></form></details>
    @if($draft)<details><summary class="small">{{ __('admin.add_lesson') }}</summary>
      <form method="POST" action="{{ route('admin.lessons.store', [$course, $mod->id]) }}">@csrf
        @include('admin.partials.tri', ['name' => 'title', 'label' => __('admin.title'), 'value' => [], 'required' => true])
        <button class="btn btn-sm">{{ __('admin.add_lesson') }}</button></form></details>@endif
  </section>
@endforeach
@endif
@if($draft)
  <section class="card"><h2>{{ __('admin.add_module') }}</h2>
    <form method="POST" action="{{ route('admin.modules.store', $course) }}">@csrf
      @include('admin.partials.tri', ['name' => 'title', 'label' => __('admin.title'), 'value' => [], 'required' => true])
      @include('admin.partials.tri', ['name' => 'description', 'label' => __('admin.description'), 'value' => [], 'textarea' => true])
      <p class="small muted">{{ __('admin.lang_fields') }}</p><button class="btn btn-sm">{{ __('admin.add_module') }}</button></form></section>
  <section class="card"><h2>{{ __('admin.impact') }} — {{ __('admin.draft') }} v{{ $draft->version_no }}</h2>
    <table><tbody>@foreach($impact as $k => $v)<tr><th scope="row">{{ __('admin.impact_rows.'.$k) }}</th><td>{{ $v }}</td></tr>@endforeach</tbody></table>
    @if(auth()->user()->isAdmin())
    <form method="POST" action="{{ route('admin.courses.publish', $course) }}" class="form">@csrf
      <fieldset><legend>{{ __('admin.apply_mode') }}</legend>
        <label class="check"><input type="radio" name="apply_mode" value="new_only" checked> {{ __('admin.apply_new_only') }}</label>
        <label class="check"><input type="radio" name="apply_mode" value="all"> {{ __('admin.apply_all') }}</label></fieldset>
      <label for="f-reason">{{ __('admin.reason') }}</label><textarea id="f-reason" name="reason" rows="2">{{ old('reason') }}</textarea>
      @error('reason')<p class="err">{{ $message }}</p>@enderror
      <div class="btns"><button class="btn">{{ __('admin.publish') }}</button></div></form>
    @else<p class="small muted">{{ __('admin.staff_only_publish') }}</p>@endif
  </section>
@endif
<details class="card"><summary>{{ __('admin.version') }}</summary><ul class="plain">@foreach($versions as $v)<li>v{{ $v->version_no }} — {{ $v->status }} {{ $v->published_at ? \App\Support\Tz::fmt($v->published_at) : '' }} {{ $v->apply_mode }} {{ $v->change_reason }}</li>@endforeach</ul></details>
@endsection
