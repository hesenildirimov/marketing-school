@extends('admin.layout')
@section('title', __('live.title'))
@section('admin')
<p><a href="{{ route('admin.live.index') }}">← {{ __('live.title') }}</a></p>
<h1>{{ $s->course->title() }} — {{ \App\Support\Tz::fmt($s->starts_at) }} @if($s->status === 'canceled')<span class="badge badge-dark">{{ __('live.canceled_badge') }}</span>@endif</h1>
<section class="card"><form method="POST" action="{{ route('admin.live.update', $s) }}" class="form">@csrf @method('PUT')
  <label for="d">{{ __('live.date') }}</label><input id="d" type="date" name="date" value="{{ \App\Support\Tz::fmt($s->starts_at, 'Y-m-d') }}" required>
  <label for="t">{{ __('live.time') }}</label><input id="t" type="text" name="time" pattern="[0-2][0-9]:[0-5][0-9]" value="{{ \App\Support\Tz::fmt($s->starts_at, 'H:i') }}" required>
  <label for="du">{{ __('live.duration') }}</label><input id="du" type="number" name="duration" min="15" max="240" value="{{ $s->duration_minutes }}">
  <label for="u">{{ __('live.meeting_url') }}</label><input id="u" type="url" name="meeting_url" value="{{ $s->meeting_url_encrypted }}" placeholder="https://zoom.us/j/...">
  @if($assignments->isNotEmpty())<fieldset><legend>{{ __('live.discuss') }}</legend>@foreach($assignments as $a)<label class="check"><input type="checkbox" name="assignments[]" value="{{ $a->id }}" @checked(in_array($a->id, $selected))> {{ tr_field($a->title) }}</label>@endforeach</fieldset>@endif
  @foreach($errors->all() as $e)<p class="err">{{ $e }}</p>@endforeach
  <div class="btns"><button class="btn">{{ __('ui.save') }}</button></div></form>
  @if($s->status === 'scheduled')<form method="POST" action="{{ route('admin.live.cancel', $s) }}">@csrf<button class="btn btn-sm btn-danger">{{ __('live.cancel') }}</button></form>@endif
</section>
<section class="card"><h2>{{ __('live.recording_upload') }}</h2>
  <div class="tus" data-ticket="{{ route('admin.live.recording', $s) }}" data-uploading="{{ __('admin.uploading') }}" data-done="{{ __('admin.upload_done') }}">
  <input type="file" accept="video/*" aria-label="{{ __('live.recording_upload') }}"><div class="progress"><span class="w-0"></span></div><p class="small muted tus-status" role="status"></p></div></section>
<section class="card"><h2>{{ __('live.questions') }} ({{ $questions->count() }})</h2><ol>@foreach($questions as $q)<li>{{ $q->body }} <small class="muted">— {{ \App\Models\User::find($q->user_id)?->name }}</small></li>@endforeach</ol></section>
@endsection
@push('scripts')<script src="{{ passet('js/tus-upload.js') }}" defer></script>@endpush
