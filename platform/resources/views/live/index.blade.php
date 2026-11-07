@extends('layouts.app')
@section('title', __('live.title'))
@section('content')
@include('course.subnav', ['course' => $course])
<h1>{{ __('live.title') }}</h1>
<p class="muted">{{ __('live.lede') }}</p>
<section class="card"><h2>{{ __('live.upcoming') }}</h2>
@forelse($upcoming as $s)
  <div class="live-row {{ $s->status === 'canceled' ? 'is-canceled' : '' }}">
    <div><b>{{ \App\Support\Tz::fmt($s->starts_at, 'd.m.Y') }}</b>, {{ \App\Support\Tz::fmt($s->starts_at, 'H:i') }}–{{ \App\Support\Tz::fmt($s->endsAt(), 'H:i') }}
      @if($s->status === 'canceled')<span class="badge badge-dark">{{ __('live.canceled_badge') }}</span>@endif</div>
    <div class="btns tight">
      @if($s->status === 'scheduled')
        @if($s->meeting_url_encrypted)<a class="btn btn-sm" href="{{ $s->meeting_url_encrypted }}" rel="noopener noreferrer" target="_blank">{{ __('live.join') }}</a>
        @else<span class="small muted">{{ __('live.link_soon') }}</span>@endif
      @endif
      <a class="btn btn-sm btn-ghost" href="{{ route('live.ics', $s) }}">{{ __('live.add_calendar') }}</a>
    </div>
  </div>
@empty<p class="muted">{{ __('live.no_upcoming') }}</p>@endforelse
</section>
@if($next)
<section class="card"><h2>{{ __('live.ask_title') }} — {{ \App\Support\Tz::fmt($next->starts_at, 'd.m.Y H:i') }}</h2>
  @if($discuss->isNotEmpty())<p class="small"><b>{{ __('live.discuss') }}:</b> {{ $discuss->map(fn ($t) => tr_field($t))->implode(', ') }}</p>@endif
  <form method="POST" action="{{ route('live.ask', $next) }}" class="form">@csrf<label for="q-body" class="sr-only">{{ __('live.ask_title') }}</label>
    <textarea id="q-body" name="body" required minlength="3" maxlength="2000" rows="3"></textarea>@error('body')<p class="err">{{ $message }}</p>@enderror
    <div class="btns"><button class="btn btn-sm">{{ __('live.ask_btn') }}</button></div></form>
  @if($mine->isNotEmpty())<h3>{{ __('live.your_questions') }}</h3><ul class="plain">@foreach($mine as $q)<li>{{ $q->body }}</li>@endforeach</ul>@endif
</section>
@endif
<section class="card"><h2>{{ __('live.recordings') }}</h2>
@forelse($recordings as $r)<p><a href="{{ route('live.recording', $r) }}">▶ {{ \App\Support\Tz::fmt($r->starts_at, 'd.m.Y') }}</a></p>@empty<p class="muted">{{ __('live.no_recordings') }}</p>@endforelse
</section>
@endsection
