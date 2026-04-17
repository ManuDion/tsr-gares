@if ($paginator->hasPages())
    <nav class="pagination-shell" role="navigation" aria-label="Pagination">
        <div class="pagination-summary">
            Affichage de <strong>{{ $paginator->firstItem() }}</strong> à <strong>{{ $paginator->lastItem() }}</strong>
            sur <strong>{{ $paginator->total() }}</strong> résultat(s)
        </div>

        <div class="pagination">
            @if ($paginator->onFirstPage())
                <span class="pagination-link is-disabled" aria-disabled="true">Précédent</span>
            @else
                <a class="pagination-link" href="{{ $paginator->previousPageUrl() }}" rel="prev">Précédent</a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="pagination-link is-disabled">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="pagination-link is-active" aria-current="page">{{ $page }}</span>
                        @else
                            <a class="pagination-link" href="{{ $url }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a class="pagination-link" href="{{ $paginator->nextPageUrl() }}" rel="next">Suivant</a>
            @else
                <span class="pagination-link is-disabled" aria-disabled="true">Suivant</span>
            @endif
        </div>
    </nav>
@endif
