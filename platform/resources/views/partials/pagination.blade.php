@if ($paginator->hasPages())
<nav class="pager" aria-label="Pagination">
  @if ($paginator->onFirstPage())<span class="btn btn-sm btn-ghost" aria-disabled="true">←</span>@else<a class="btn btn-sm btn-ghost" href="{{ $paginator->previousPageUrl() }}" rel="prev">←</a>@endif
  @if(method_exists($paginator, 'lastPage'))<span class="small muted">{{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>@endif
  @if ($paginator->hasMorePages())<a class="btn btn-sm btn-ghost" href="{{ $paginator->nextPageUrl() }}" rel="next">→</a>@else<span class="btn btn-sm btn-ghost" aria-disabled="true">→</span>@endif
</nav>
@endif
