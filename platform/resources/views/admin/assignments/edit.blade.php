@extends('admin.layout')
@section('title', $a->label())
@section('admin')
<p><a href="{{ route('admin.courses.show', $course) }}">← {{ $course->title() }}</a></p>
<h1>{{ $a->label() }}</h1>
<section class="card"><form method="POST" action="{{ route('admin.assignments.update', [$course, $a]) }}" enctype="multipart/form-data">@csrf @method('PUT')
  @include('admin.partials.tri', ['name' => 'title', 'label' => __('admin.title'), 'value' => $a->revision?->title ?? [], 'required' => true])
  @include('admin.partials.tri', ['name' => 'body', 'label' => __('assess.body'), 'value' => $a->revision?->body ?? [], 'textarea' => true, 'required' => true])
  <label for="f-due">{{ __('assess.due') }}</label><input id="f-due" type="datetime-local" name="due_at" value="{{ $a->revision?->due_at ? \App\Support\Tz::local($a->revision->due_at)->format('Y-m-d\TH:i') : '' }}">
  <label for="f-case">{{ __('assess.case_file') }}</label><input id="f-case" type="file" name="case_file" accept=".pdf,.docx,.xlsx,.pptx,.png,.jpg">
  @error('case_file')<p class="err">{{ $message }}</p>@enderror
  <div class="btns"><button class="btn">{{ __('ui.save') }}</button></div></form></section>
<section class="card"><h2>{{ __('assess.max_points') }}</h2><p class="small muted">{{ __('assess.scoring_note') }}</p>
  <table><tbody>@foreach($scoring as $vid => $row)<tr><th>{{ $vid === $draft?->id ? __('admin.draft') : __('admin.current_version') }}</th><td>{{ $row->max_points }}</td><td>{{ $row->is_mandatory ? __('assess.mandatory') : __('assess.optional') }}</td></tr>@endforeach</tbody></table>
  @if($draft && isset($scoring[$draft->id]))
  <form method="POST" action="{{ route('admin.assignments.scoring', [$course, $a]) }}" class="form">@csrf @method('PUT')
    <label for="f-mp">{{ __('assess.max_points') }}</label><input id="f-mp" type="number" name="max_points" min="1" max="1000" value="{{ $scoring[$draft->id]->max_points }}">
    <label class="check"><input type="checkbox" name="is_mandatory" value="1" @checked($scoring[$draft->id]->is_mandatory)> {{ __('assess.mandatory') }}</label>
    @if($graded)<label for="f-reason">{{ __('admin.reason') }}</label><textarea id="f-reason" name="reason" rows="2"></textarea>@endif
    @error('max_points')<p class="err">{{ $message }}</p>@enderror @error('reason')<p class="err">{{ $message }}</p>@enderror
    <div class="btns"><button class="btn btn-sm">{{ __('ui.save') }}</button></div></form>
  @endif
</section>
@endsection
