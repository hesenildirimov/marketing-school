@extends('layouts.app')
@section('title', $lesson->label().' — '.$course->title())
@push('head')
@if($canView && $revision?->video_asset_id && config('services.bunny.cdn_host'))<link rel="preconnect" href="https://{{ config('services.bunny.cdn_host') }}" crossorigin><link rel="dns-prefetch" href="https://{{ config('services.bunny.cdn_host') }}">@endif
@endpush
@section('content')
@include('course.subnav', ['course' => $course])
<div class="course-top">
  <div><a class="small muted" href="{{ route('dashboard') }}">{{ __('course.my_courses') }}</a><h1 class="course-h">{{ $course->title() }}</h1></div>
  <div class="course-progress"><span class="small">{{ __('course.progress') }}: <b>{{ $progress['percent'] }}%</b></span>
    <div class="progress" role="progressbar" aria-valuenow="{{ $progress['percent'] }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ __('course.progress') }}"><span class="w-{{ $progress['percent'] }}"></span></div></div>
</div>
@if($demo)<p class="alert">{{ __('course.demo_until', ['date' => \App\Support\Tz::fmt(\Carbon\Carbon::parse($demo->expires_at)), 'n' => count($manifest->demoModuleIds())]) }} @if(Route::has('billing.checkout'))<a href="{{ route('course.show', $course) }}#buy">{{ __('course.buy', ['price' => price_label($course)]) }}</a>@endif</p>@endif

<div class="lesson-layout">
  <aside class="lesson-nav" aria-label="{{ __('course.modules') }}">
    <details open class="nav-panel"><summary>{{ __('course.modules') }}</summary>
    @foreach($manifest->modules as $mi => $mod)
      @php($done = $moduleDone($mod->id))
      <details class="mod" @if($manifest->moduleOf($lesson->id)?->id === $mod->id) open @endif>
        <summary><span class="mod-n">{{ __('course.module_n', ['n' => $mi + 1]) }}</span> {{ tr_field($mod->title) }} @if($done)<span class="ok" title="{{ __('course.done') }}">✓</span>@endif</summary>
        <ol class="lessons">
        @foreach($manifest->lessonsOf($mod->id) as $l)
          @php($isLocked = $locked($l))
          <li class="{{ $l->id === $lesson->id ? 'cur' : '' }} {{ $isLocked ? 'is-locked' : '' }}">
            @if($isLocked)<span><span class="lock" aria-hidden="true">🔒</span> {{ tr_field($l->title) }} <small class="muted">{{ __('course.locked') }}</small></span>
            @else<a href="{{ route('lesson.show', [$course, $l->id]) }}" @if($l->id === $lesson->id) aria-current="page" @endif>
              @if(isset($progress['rows'][$l->id]) && $progress['rows'][$l->id]->completed_at)<span class="ok" aria-label="{{ __('course.done') }}">✓</span>@endif {{ tr_field($l->title) }}</a>@endif
          </li>
        @endforeach
        </ol>
      </details>
    @endforeach
    </details>
  </aside>

  <section class="lesson-main">
    @if(! $canView)
      <div class="card locked-card"><h2>🔒 {{ __('course.locked_title') }}</h2><p>{{ __('course.locked') }}.</p>
        @if(Route::has('billing.checkout'))<form method="POST" action="{{ route('billing.checkout', $course) }}">@csrf<button class="btn">{{ __('course.buy', ['price' => price_label($course)]) }}</button></form>@endif</div>
    @else
      @if($revision?->video_asset_id)
      <div class="player" id="player" tabindex="0" aria-label="Video"
           data-playback="{{ route('api.playback', $lesson->id) }}" data-progress="{{ route('api.progress', $lesson->id) }}"
           data-metrics="{{ route('api.metrics', $lesson->id) }}" data-heartbeat="{{ config('platform.video.heartbeat_seconds') }}"
           data-next="{{ $next ? route('lesson.show', [$course, $next->id]) : '' }}" data-next-playback="{{ $next && ! $locked($next) ? route('api.playback', $next->id) : '' }}"
           data-buy="{{ route('course.show', $course) }}" data-msg-ended="{{ __('course.access_ended') }}"
           data-msg-retry="{{ __('course.net_retry') }}" data-msg-buy="{{ __('course.buy', ['price' => price_label($course)]) }}">
        <video id="video" playsinline preload="none" controlslist="nodownload nofullscreen noremoteplayback" disablepictureinpicture></video>
        <button class="poster" type="button" aria-label="{{ __('course.play') }}"><span class="play-ico" aria-hidden="true">▶</span></button>
        <div class="wm" aria-hidden="true"></div>
        <div class="p-msg" role="status" hidden></div>
        <div class="controls" hidden>
          <button type="button" data-act="play" aria-label="{{ __('course.play') }}">▶</button>
          <span class="time"><span data-t="cur">0:00</span> / <span data-t="dur">0:00</span></span>
          <input type="range" min="0" max="1000" value="0" step="1" data-act="seek" aria-label="{{ __('course.seek') }}">
          <button type="button" data-act="mute" aria-label="{{ __('course.mute') }}">🔊</button>
          <label class="sr-only" for="p-speed">{{ __('course.speed') }}</label>
          <select id="p-speed" data-act="speed">@foreach([0.75, 1, 1.25, 1.5, 1.75, 2] as $s)<option value="{{ $s }}" @selected($s == 1)>{{ $s }}×</option>@endforeach</select>
          <label class="sr-only" for="p-quality">{{ __('course.quality') }}</label>
          <select id="p-quality" data-act="quality"><option value="-1">{{ __('course.auto') }}</option></select>
          @if($revision->chapters)<label class="sr-only" for="p-ch">{{ __('course.chapters') }}</label>
          <select id="p-ch" data-act="chapter"><option value="">{{ __('course.chapters') }}</option>@foreach($revision->chapters as $ch)<option value="{{ (int) $ch['t'] }}">{{ gmdate($ch['t'] >= 3600 ? 'G:i:s' : 'i:s', $ch['t']) }} {{ tr_field($ch['title']) }}</option>@endforeach</select>@endif
          <button type="button" data-act="fs" aria-label="{{ __('course.fullscreen') }}">⛶</button>
        </div>
      </div>
      <p class="small muted">{{ __('course.shortcuts') }} {{ __('course.video_lang_note') }}</p>
      @else
      <div class="card"><p>{{ __('course.no_video') }}</p></div>
      @endif

      <div class="lesson-head">
        <h2>{{ $lesson->label() }}</h2>
        <div class="btns">
          @if(isset($progress['rows'][$lesson->id]) && $progress['rows'][$lesson->id]->completed_at)
            <span class="badge">✓ {{ __('course.completed') }}</span>
          @else
            <form method="POST" action="{{ route('api.complete', $lesson->id) }}">@csrf<button class="btn" type="submit">{{ __('course.complete_btn') }}</button></form>
          @endif
          @if($next && ! $locked($next))<a class="btn btn-ghost" href="{{ route('lesson.show', [$course, $next->id]) }}">{{ __('course.next') }} →</a>@endif
        </div>
      </div>
      @if($revision?->body)<div class="prose">{!! nl2br(e(tr_field($revision->body))) !!}</div>@endif

      @php($tabs = array_filter(['homework' => $paid, 'materials' => true, 'notes' => true, 'questions' => $paid]))
      <div class="tabs" role="tablist" aria-label="{{ $lesson->label() }}">
        @foreach(array_keys($tabs) as $i => $t)<button type="button" role="tab" id="tab-{{ $t }}" aria-controls="panel-{{ $t }}" aria-selected="{{ $i === 0 ? 'true' : 'false' }}" @if($i) tabindex="-1" @endif>{{ __('course.tab_'.$t) }}</button>@endforeach
      </div>
      @foreach(array_keys($tabs) as $i => $t)
      <div class="tab-panel card" role="tabpanel" id="panel-{{ $t }}" aria-labelledby="tab-{{ $t }}" @if($i) hidden @endif>
        @if($t === 'homework')
          @includeFirst(['assignments.panel', 'partials.empty-panel'], ['course' => $course, 'lesson' => $lesson])
        @elseif($t === 'materials')
          @if($revision?->materials)<ul class="plain">@foreach($revision->materials as $mat)<li><a href="{{ $mat['url'] }}" rel="noopener noreferrer" target="_blank">{{ $mat['title'] }}</a></li>@endforeach</ul>
          @else<p class="muted">{{ __('course.no_materials') }}</p>@endif
        @elseif($t === 'notes')
          <p class="small muted">{{ __('course.notes_private') }}</p>
          <form method="POST" action="{{ route('api.notes.store', $lesson->id) }}" class="note-form">@csrf
            <input type="hidden" name="at_second" value="">
            <label for="note-body" class="sr-only">{{ __('course.note_add') }}</label>
            <textarea id="note-body" name="body" maxlength="5000" required rows="3"></textarea>
            <div class="btns"><button class="btn btn-sm" type="submit">{{ __('course.note_add') }}</button><span class="small muted note-at" data-tpl="{{ __('course.note_at', ['t' => '__T__']) }}"></span></div>
          </form>
          <ul class="notes">@foreach($notes as $n)<li>
            @if($n->at_second !== null)<button type="button" class="link ts" data-seek="{{ $n->at_second }}">{{ gmdate($n->at_second >= 3600 ? 'G:i:s' : 'i:s', $n->at_second) }}</button>@endif
            <span class="note-body">{{ $n->body }}</span>
            <form method="POST" action="{{ route('api.notes.delete', [$lesson->id, $n->id]) }}">@csrf @method('DELETE')<button class="link small" type="submit">{{ __('course.note_delete') }}</button></form>
          </li>@endforeach</ul>
        @elseif($t === 'questions')
          @includeFirst(['live.questions-panel', 'partials.empty-panel'], ['course' => $course])
        @endif
      </div>
      @endforeach
    @endif
  </section>
</div>
@endsection
@push('scripts')
<script src="{{ passet('js/lesson.js') }}" defer></script>
@if($canView && $revision?->video_asset_id)
<script src="{{ passet('vendor/hls.light.min.js') }}" defer></script>
<script src="{{ passet('js/player.js') }}" defer></script>
@endif
@endpush
