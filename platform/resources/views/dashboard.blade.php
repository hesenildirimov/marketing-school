@extends('layouts.app')
@section('title', __('course.my_courses'))
@section('content')
<div class="page-head"><h1>{{ __('course.my_courses') }}</h1><p class="muted small">{{ __('course.video_lang_note') }}</p></div>
@stack('dashboard-top')
<div class="grid">
@foreach($cards as $c)
  @php($course = $c['course'])
  <article class="card course-card">
    <div class="c-row"><h2><a href="{{ route('course.show', $course) }}">{{ $course->title() }}</a></h2>
      <span class="badge {{ in_array($c['state'], ['active', 'staff', 'demo']) ? '' : 'badge-muted' }}">{{ __('course.status.'.$c['state']) }}</span></div>
    @if(isset($c['progress']))
      <div class="progress" role="progressbar" aria-valuenow="{{ $c['progress']['percent'] }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ __('course.progress') }}"><span class="w-{{ $c['progress']['percent'] }}"></span></div>
      <p class="small muted">{{ __('course.lessons_done', ['done' => $c['progress']['completed'], 'total' => $c['progress']['total']]) }} · {{ $c['progress']['percent'] }}%</p>
    @endif
    @php($cert = \App\Models\Certificate::where('user_id', $user->id)->where('course_id', $course->id)->whereNull('revoked_at')->first())
    @if($cert)<p class="alert small">🎓 {{ __('assess.diploma_ready') }} — <a href="{{ route('certificates.pdf', $cert) }}">{{ __('assess.diploma_download') }}</a> · <a href="{{ route('certificates.verify', $cert->verify_id) }}">{{ __('assess.verify_link') }}</a></p>@endif
    @if(isset($c['enrollment']) && in_array($c['state'], ['active', 'past_due']))
      @php($sc = app(\App\Domain\Certificates\DiplomaService::class)->score($c['enrollment']))
      @if($sc['possible'] > 0)<p class="small">{{ __('assess.total', ['earned' => $sc['earned'], 'possible' => $sc['possible'], 'percent' => number_format($sc['percent_x10'] / 10, 1)]) }}</p>@endif
    @endif
    @if($c['state'] === 'active' && $c['enrollment']?->paid_until)<p class="small">{{ __('course.paid_until', ['date' => \App\Support\Tz::fmt($c['enrollment']->paid_until, 'd.m.Y')]) }}</p>@endif
    @php($sub = isset($c['enrollment']) && $c['enrollment'] ? \App\Models\Subscription::where('enrollment_id', $c['enrollment']->id)->where('status', '<>', 'canceled')->first() : null)
    @if($sub && in_array($c['state'], ['active', 'past_due']))
      <div class="sub-box small"><b>{{ __('billing.auto_renew') }}:</b>
        @if(! $sub->cancel_at_period_end)
          {{ __('billing.auto_renew_on_label', ['date' => \App\Support\Tz::fmt($sub->next_charge_at, 'd.m.Y'), 'amount' => price_label($course), 'last4' => $sub->card_last4 ?? '••••']) }}
          <form method="POST" action="{{ route('billing.cancel', $sub) }}">@csrf<button class="link small">{{ __('billing.auto_renew_cancel') }}</button></form>
        @else
          {{ __('billing.auto_renew_off_label', ['date' => \App\Support\Tz::fmt($c['enrollment']->paid_until, 'd.m.Y')]) }}
          <form method="POST" action="{{ route('billing.resume', $sub) }}">@csrf<button class="link small">{{ __('billing.auto_renew_resume') }}</button></form>
        @endif
        @error('subscription')<p class="err">{{ $message }}</p>@enderror
      </div>
    @endif
    @if($c['state'] === 'past_due')<form method="POST" action="{{ route('billing.checkout', $course) }}">@csrf<button class="btn btn-sm">{{ __('billing.renew_now') }}</button></form>@endif
    @if($c['state'] === 'past_due')<p class="alert small">{{ __('course.grace_note', ['date' => \App\Support\Tz::fmt($c['grace_until'])]) }}</p>@endif
    @if($c['state'] === 'demo')<p class="small">{{ __('course.demo_until', ['date' => \App\Support\Tz::fmt(\Carbon\Carbon::parse($c['demo']->expires_at)), 'n' => count(app(\App\Domain\Access\AccessPolicy::class)->manifestFor($user, $course)?->demoModuleIds() ?? [])]) }}</p>@endif
    <div class="btns">
      @if(in_array($c['state'], ['active', 'past_due', 'staff', 'demo']))<a class="btn" href="{{ route('course.show', $course) }}">{{ __('course.continue') }}</a>@endif
      @if(! in_array($c['state'], ['active', 'staff']))
        @if(Route::has('billing.checkout'))<form method="POST" action="{{ route('billing.checkout', $course) }}">@csrf<button class="btn {{ $c['state'] === 'demo' ? 'btn-ghost' : '' }}">{{ __('course.buy', ['price' => price_label($course)]) }}</button></form>
        @else<a class="btn btn-ghost" href="{{ route('course.show', $course) }}">{{ __('course.buy', ['price' => price_label($course)]) }}</a>@endif
      @endif
      @if($c['state'] === 'available' && ! app(\App\Domain\Access\AccessPolicy::class)->demoClaim($user) && Route::has('demo.start'))
        <form method="POST" action="{{ route('demo.start', $course) }}">@csrf<button class="btn btn-ghost">{{ __('demo.start') }}</button></form>
      @endif
      @if(in_array($c['state'], ['active', 'past_due', 'staff']))<a class="btn btn-ghost" href="{{ route('live.index', $course) }}">{{ __('live.title') }}</a>
        <a class="btn btn-ghost" href="{{ route('mentoring.index', $course) }}">{{ __('mentoring.title') }}</a>@endif
      @stack('card-actions-'.$course->id)
    </div>
  </article>
@endforeach
</div>
@endsection
