@extends('layouts.app')
@section('title', __('live.recordings'))
@section('content')
@include('course.subnav', ['course' => $course])
<h1>{{ __('live.recordings') }} — {{ \App\Support\Tz::fmt($s->starts_at, 'd.m.Y') }}</h1>
<div class="player" id="player" tabindex="0" aria-label="Video" data-playback="{{ route('api.live.playback', $s) }}" data-heartbeat="15"
     data-buy="{{ route('course.show', $course) }}" data-msg-ended="{{ __('course.access_ended') }}" data-msg-retry="{{ __('course.net_retry') }}" data-msg-buy="{{ __('course.buy', ['price' => price_label($course)]) }}">
  <video id="video" playsinline preload="none" controlslist="nodownload nofullscreen noremoteplayback" disablepictureinpicture></video>
  <button class="poster" type="button" aria-label="{{ __('course.play') }}"><span class="play-ico" aria-hidden="true">▶</span></button>
  <div class="wm" aria-hidden="true"></div><div class="p-msg" role="status" hidden></div>
  <div class="controls" hidden>
    <button type="button" data-act="play" aria-label="{{ __('course.play') }}">▶</button>
    <span class="time"><span data-t="cur">0:00</span> / <span data-t="dur">0:00</span></span>
    <input type="range" min="0" max="1000" value="0" step="1" data-act="seek" aria-label="{{ __('course.seek') }}">
    <button type="button" data-act="mute" aria-label="{{ __('course.mute') }}">🔊</button>
    <label class="sr-only" for="p-speed">{{ __('course.speed') }}</label><select id="p-speed" data-act="speed">@foreach([0.75, 1, 1.25, 1.5, 1.75, 2] as $sp)<option value="{{ $sp }}" @selected($sp == 1)>{{ $sp }}×</option>@endforeach</select>
    <label class="sr-only" for="p-quality">{{ __('course.quality') }}</label><select id="p-quality" data-act="quality"><option value="-1">{{ __('course.auto') }}</option></select>
    <button type="button" data-act="fs" aria-label="{{ __('course.fullscreen') }}">⛶</button>
  </div>
</div>
@endsection
@push('scripts')<script src="{{ passet('vendor/hls.light.min.js') }}" defer></script><script src="{{ passet('js/player.js') }}" defer></script>@endpush
