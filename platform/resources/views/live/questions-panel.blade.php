@php($nextLive = \App\Models\LiveSession::where('course_id', $course->id)->where('status', 'scheduled')->where('starts_at', '>', now())->orderBy('starts_at')->first())
@if($nextLive)
  <p class="small">{{ __('live.ask_title') }}: <b>{{ \App\Support\Tz::fmt($nextLive->starts_at) }}</b></p>
  <form method="POST" action="{{ route('live.ask', $nextLive) }}">@csrf<label for="lq" class="sr-only">{{ __('live.ask_title') }}</label>
    <textarea id="lq" name="body" required minlength="3" maxlength="2000" rows="3"></textarea><div class="btns"><button class="btn btn-sm">{{ __('live.ask_btn') }}</button>
    <a href="{{ route('live.index', $course) }}">{{ __('live.title') }} →</a></div></form>
@else<p class="muted">{{ __('live.no_upcoming') }}</p>@endif
