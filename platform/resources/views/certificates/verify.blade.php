@extends('layouts.app')
@section('title', __('assess.verify_title'))
@section('content')
<div class="auth-box card">
  <h1>{{ __('assess.verify_title') }}</h1>
  @if($revoked)<p class="alert alert-strong">{{ __('assess.verify_revoked') }}</p>@else<p class="alert">✓ {{ __('assess.verify_valid') }}</p>@endif
  <dl class="kv"><dt>{{ __('assess.holder') }}</dt><dd>{{ $holder }}</dd><dt>{{ __('assess.course') }}</dt><dd>{{ $course }}</dd>
  <dt>{{ __('assess.issued') }}</dt><dd>{{ $issued->setTimezone(config('platform.display_timezone'))->format('d.m.Y') }}</dd><dt>ID</dt><dd><code>{{ $verifyId }}</code></dd></dl>
  @if(! $revoked)<div class="btns"><a class="btn btn-ghost" href="{{ $linkedin }}" rel="noopener noreferrer" target="_blank">{{ __('assess.linkedin') }}</a></div>@endif
</div>
@endsection
