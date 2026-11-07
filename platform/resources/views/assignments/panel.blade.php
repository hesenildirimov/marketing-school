@php
  $acc = app(\App\Domain\Access\AccessPolicy::class);
  $enr = $acc->enrollment(auth()->user(), $course);
  $ver = ($enr && $acc->hasPaidAccess(auth()->user(), $course)) ? $enr->course_version_id : $course->current_version_id;
  $rows = app(\App\Domain\Assessment\AssignmentService::class)->forVersion($ver, $lesson->module_id, auth()->id());
  $score = $enr ? app(\App\Domain\Certificates\DiplomaService::class)->score($enr) : null;
  $rules = \App\Models\BillingRule::for($course->id);
@endphp
@if($score && $score['possible'] > 0)<p class="alert small">{{ __('assess.total', ['earned' => $score['earned'], 'possible' => $score['possible'], 'percent' => number_format($score['percent_x10'] / 10, 1)]) }}</p>@endif
@forelse($rows as $a)
  <article class="assignment">
    <div class="c-row"><h3>{{ tr_field($a->title) }}</h3>
      <span><span class="badge {{ $a->is_mandatory ? '' : 'badge-muted' }}">{{ $a->is_mandatory ? __('assess.mandatory') : __('assess.optional') }}</span>
      <span class="badge badge-muted">{{ __('assess.max_points') }}: {{ $a->max_points }}</span>
      <span class="badge badge-dark">{{ __('assess.status.'.($a->submission->status ?? 'not_submitted')) }}</span></span></div>
    @if($a->due_at)<p class="small muted">{{ __('assess.due') }}: {{ \App\Support\Tz::fmt(\Carbon\Carbon::parse($a->due_at)) }}</p>@endif
    <div class="prose">{!! nl2br(e(tr_field($a->body))) !!}</div>
    @if($a->case_file_id && ($cf = \App\Models\UploadedFile::find($a->case_file_id)) && $cf->isDownloadable())
      <p><a href="{{ \App\Http\Controllers\FileController::link($cf) }}">📎 {{ __('assess.case_file') }}: {{ $cf->original_name }}</a></p>@endif
    @if($a->grade)
      <div class="card grade-box"><b>{{ __('assess.score') }}: {{ $a->grade->points }} / {{ $a->grade->max_points_snapshot }}</b>
        @if($a->grade->feedback)<p class="small"><b>{{ __('assess.feedback') }}:</b><br>{!! nl2br(e($a->grade->feedback)) !!}</p>@endif</div>
    @endif
    @if($a->submission)
      @php($sub = \App\Models\Submission::find($a->submission->id))
      <details><summary class="small">{{ __('assess.your_answer') }}</summary><div class="prose answer">{!! $sub->body_html !!}</div>
        <ul class="plain small">@foreach($sub->files as $f)<li>@if($f->isDownloadable())<a href="{{ \App\Http\Controllers\FileController::link($f) }}">{{ $f->original_name }}</a>@else{{ $f->original_name }}@endif — {{ __('assess.file_status.'.$f->scan_status) }}</li>@endforeach</ul></details>
    @endif
    @if(! $a->submission || $a->submission->status !== 'graded')
    <form method="POST" action="{{ route('assignments.submit', $a->id) }}" enctype="multipart/form-data" class="submit-form" data-upload-init="{{ route('api.uploads.init', $a->id) }}">@csrf
      <label for="ans-{{ $a->id }}">{{ __('assess.your_answer') }}</label>
      <textarea id="ans-{{ $a->id }}" name="body" rows="6" maxlength="20000">{{ old('body') }}</textarea>
      <label for="files-{{ $a->id }}">{{ __('assess.files') }}</label>
      <input id="files-{{ $a->id }}" type="file" name="files[]" multiple accept=".pdf,.docx,.xlsx,.pptx,.png,.jpg,.jpeg">
      <p class="small muted">{{ __('assess.files_hint', ['mb' => $rules->upload_file_mb, 'n' => $rules->upload_files_per_submission]) }}</p>
      @error('files')<p class="err">{{ $message }}</p>@enderror @error('body')<p class="err">{{ $message }}</p>@enderror
      <div class="btns"><button class="btn btn-sm" type="submit">{{ $a->submission ? __('assess.resubmit') : __('assess.submit') }}</button><span class="small muted up-status" role="status"></span></div>
    </form>
    @endif
  </article>
@empty
  <p class="muted">{{ __('assess.no_assignments') }}</p>
@endforelse
@push('scripts')<script src="{{ passet('js/chunk-upload.js') }}" defer></script>@endpush
