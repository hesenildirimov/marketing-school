@extends('layouts.app')
@section('title', __('billing.checking'))
@section('content')
<div class="auth-box card" id="pay-status" data-status-url="{{ route('api.billing.status', $order) }}" data-status="{{ $order->status }}">
  @if($order->status === 'paid')
    <h1>✓ {{ __('billing.paid') }}</h1><p>{{ __('billing.paid_lede') }}</p>
    <div class="btns"><a class="btn" href="{{ route('course.show', $course) }}">{{ __('billing.go_course') }}</a></div>
  @elseif(in_array($order->status, ['failed', 'refunded', 'charged_back']))
    <h1>{{ __('billing.failed') }}</h1><p>{{ __('billing.failed_lede') }}</p>
    <form method="POST" action="{{ route('billing.checkout', $course) }}">@csrf<button class="btn">{{ __('billing.retry') }}</button></form>
  @else
    <h1>{{ __('billing.checking') }}</h1><p class="muted">{{ __('billing.checking_lede') }}</p>
  @endif
</div>
@endsection
@push('scripts')<script src="{{ passet('js/pay-status.js') }}" defer></script>@endpush
