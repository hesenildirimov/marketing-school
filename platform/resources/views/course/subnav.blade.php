@php($acc = app(\App\Domain\Access\AccessPolicy::class))
@if($acc->canSeePaidFeatures(auth()->user(), $course))
<nav class="btns tight course-subnav" aria-label="{{ $course->title() }}">
  <a class="btn btn-sm {{ request()->routeIs('course.show', 'lesson.show') ? '' : 'btn-ghost' }}" href="{{ route('course.show', $course) }}">{{ $course->title() }}</a>
  <a class="btn btn-sm {{ request()->routeIs('live.*') ? '' : 'btn-ghost' }}" href="{{ route('live.index', $course) }}">{{ __('live.title') }}</a>
  <a class="btn btn-sm {{ request()->routeIs('mentoring.*') ? '' : 'btn-ghost' }}" href="{{ route('mentoring.index', $course) }}">{{ __('mentoring.title') }}</a>
</nav>
@endif
