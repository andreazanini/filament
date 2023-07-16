@props([
    'results',
])

<div
    x-data="{ isOpen: true }"
    x-show="isOpen"
    x-on:keydown.escape.window="isOpen = false"
    x-on:click.away="isOpen = false"
    x-on:open-global-search-results.window="isOpen = true"
    {{ $attributes->class(['fi-global-search-results-ctn absolute end-0 top-auto z-10 mt-2 w-screen max-w-xs overflow-hidden rounded-xl shadow-xl ring-1 ring-gray-950/5 dark:ring-white/10 sm:max-w-lg']) }}
>
    <div class="max-h-96 overflow-x-hidden bg-white dark:bg-gray-900">
        @forelse ($results->getCategories() as $group => $groupedResults)
            <x-filament::global-search.result-group
                :label="$group"
                :results="$groupedResults"
            />
        @empty
            <x-filament::global-search.no-results-message />
        @endforelse
    </div>
</div>