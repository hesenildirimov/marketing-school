@extends('admin.layout')
@section('title', $lesson->label())
@section('admin')
<p><a href="{{ route('admin.courses.show', $course) }}">← {{ $course->title() }}</a></p>
<h1>{{ $lesson->label() }}</h1>
<section class="card"><h2>{{ __('admin.video') }}</h2>
  @if($video)<p>{{ __('admin.video_status') }}: <span class="badge">{{ $video->status }}</span> @if($video->duration_seconds) · {{ gmdate('H:i:s', $video->duration_seconds) }} @endif</p>
  <form method="POST" action="{{ route('admin.lessons.video.refresh', [$course, $lesson]) }}">@csrf<button class="btn btn-sm btn-ghost">{{ __('admin.video_refresh') }}</button></form>@endif
  <div class="tus" data-ticket="{{ route('admin.lessons.video', [$course, $lesson]) }}" data-uploading="{{ __('admin.uploading') }}" data-done="{{ __('admin.upload_done') }}">
    <label for="f-video">{{ __('admin.video_upload') }}</label><input id="f-video" type="file" accept="video/mp4,video/quicktime,video/x-matroska,video/webm">
    <div class="progress"><span class="w-0"></span></div><p class="small muted tus-status" role="status"></p></div>
</section>
<section class="card"><form method="POST" action="{{ route('admin.lessons.update', [$course, $lesson]) }}">@csrf @method('PUT')
  @include('admin.partials.tri', ['name' => 'title', 'label' => __('admin.title'), 'value' => $rev?->title ?? [], 'required' => true])
  @include('admin.partials.tri', ['name' => 'body', 'label' => __('admin.body'), 'value' => $rev?->body ?? [], 'textarea' => true])
  <label for="f-chapters">{{ __('admin.chapters') }}</label>
  <textarea id="f-chapters" name="chapters" rows="4">{{ old('chapters', collect($rev?->chapters ?? [])->map(fn ($c) => gmdate($c['t'] >= 3600 ? 'G:i:s' : 'i:s', $c['t']).' '.tr_field($c['title']))->implode("\n")) }}</textarea>
  <label for="f-materials">{{ __('admin.materials') }}</label>
  <textarea id="f-materials" name="materials" rows="3">{{ old('materials', collect($rev?->materials ?? [])->map(fn ($m) => $m['title'].' | '.$m['url'])->implode("\n")) }}</textarea>
  <label for="f-note">{{ __('admin.change_note') }}</label><input id="f-note" type="text" name="change_note" maxlength="500">
  <div class="btns"><button class="btn">{{ __('ui.save') }}</button></div>
</form></section>
@stack('lesson-admin')
<details class="card"><summary>{{ __('admin.revisions') }} ({{ $revisions->count() }})</summary><ul class="plain">@foreach($revisions as $r)<li>#{{ $r->revision_no }} · {{ \App\Support\Tz::fmt($r->created_at) }} · {{ tr_field($r->title) }} {{ $r->change_note ? '— '.$r->change_note : '' }}</li>@endforeach</ul></details>
@endsection
@push('scripts')<script src="{{ passet('js/tus-upload.js') }}" defer></script>@endpush
