@if($paginator->hasPages())
    <nav class="pagination-shell" role="navigation" aria-label="صفحه‌بندی">
        <div class="pagination-meta">
            نمایش <strong>{{ number_format($paginator->firstItem()) }}</strong> تا <strong>{{ number_format($paginator->lastItem()) }}</strong>
            از <strong>{{ number_format($paginator->total()) }}</strong> مورد
        </div>

        <div class="pagination-list">
            @if($paginator->onFirstPage())
                <span class="pagination-nav pagination-disabled"><x-icon name="arrow-left" class="size-3.5 rotate-180" />قبلی</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="pagination-nav"><x-icon name="arrow-left" class="size-3.5 rotate-180" />قبلی</a>
            @endif

            @foreach($elements as $element)
                @if(is_string($element))
                    <span class="pagination-ellipsis">{{ $element }}</span>
                @endif

                @if(is_array($element))
                    @foreach($element as $page => $url)
                        @if((int) $page === $paginator->currentPage())
                            <span class="pagination-link active" aria-current="page">{{ number_format($page) }}</span>
                        @else
                            <a href="{{ $url }}" class="pagination-link" aria-label="صفحه {{ $page }}">{{ number_format($page) }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="pagination-nav">بعدی<x-icon name="arrow-left" class="size-3.5" /></a>
            @else
                <span class="pagination-nav pagination-disabled">بعدی<x-icon name="arrow-left" class="size-3.5" /></span>
            @endif
        </div>
    </nav>
@endif
