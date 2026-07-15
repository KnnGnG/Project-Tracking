@props(['rows' => 4])

<div {{ $attributes->merge(['class' => 'space-y-3 p-5']) }} role="status" aria-label="Loading content">
    @foreach(range(1, max(1, (int) $rows)) as $row)
        <div class="flex items-center gap-4" aria-hidden="true">
            <div class="ui-skeleton h-9 w-9 flex-none rounded-full"></div>
            <div class="min-w-0 flex-1 space-y-2">
                <div class="ui-skeleton h-3 w-1/3"></div>
                <div class="ui-skeleton h-3 w-2/3"></div>
            </div>
            <div class="ui-skeleton hidden h-7 w-20 sm:block"></div>
        </div>
    @endforeach
    <span class="sr-only">Loading...</span>
</div>
