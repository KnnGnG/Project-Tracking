<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('My Projects') }}
            </h2>
            <p class="mt-1 text-sm text-gray-500">
                Choose a project to open the dashboard for your role in that project.
            </p>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if($projects->isEmpty())
                <div class="bg-white border border-gray-200 shadow-sm sm:rounded-lg px-6 py-12 text-center">
                    <h3 class="text-lg font-semibold text-gray-900">No assigned projects yet</h3>
                    <p class="mt-2 text-sm text-gray-500">
                        Projects will appear here once you are added as a team lead or member.
                    </p>
                </div>
            @else
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach($projects as $item)
                        @php
                            $project = $item['project'];
                            $roleLabel = $item['role'] === 'lead' ? 'Team Lead' : 'Member';
                            $roleClass = $item['role'] === 'lead'
                                ? 'bg-indigo-50 text-indigo-700 border-indigo-100'
                                : 'bg-emerald-50 text-emerald-700 border-emerald-100';
                            $statusClass = match($project->status) {
                                'active' => 'bg-green-100 text-green-700',
                                'on_hold' => 'bg-yellow-100 text-yellow-700',
                                'completed' => 'bg-blue-100 text-blue-700',
                                default => 'bg-gray-100 text-gray-600',
                            };
                        @endphp

                        <a href="{{ route('projects.open', $project) }}"
                           class="group block rounded-lg border border-gray-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-indigo-300 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <h3 class="truncate text-lg font-semibold text-gray-900 group-hover:text-indigo-700">
                                        {{ $project->name }}
                                    </h3>
                                    <p class="mt-1 text-sm text-gray-500">
                                        {{ $item['teams']->count() }} team{{ $item['teams']->count() !== 1 ? 's' : '' }}
                                    </p>
                                </div>
                                <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClass }}">
                                    {{ ucfirst(str_replace('_', ' ', $project->status)) }}
                                </span>
                            </div>

                            @if($project->description)
                                <p class="mt-4 line-clamp-2 text-sm text-gray-600">
                                    {{ $project->description }}
                                </p>
                            @endif

                            <div class="mt-5 flex flex-wrap items-center gap-2">
                                <span class="inline-flex rounded-full border px-3 py-1 text-xs font-semibold {{ $roleClass }}">
                                    Opens as {{ $roleLabel }}
                                </span>
                                @foreach($item['teams']->take(2) as $team)
                                    <span class="inline-flex rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-600">
                                        {{ $team->name }}
                                    </span>
                                @endforeach
                            </div>

                            <div class="mt-5 flex items-center justify-between border-t border-gray-100 pt-4 text-sm">
                                <span class="text-gray-500">
                                    {{ $project->start_date?->format('M d, Y') }} - {{ $project->end_date?->format('M d, Y') }}
                                </span>
                                <span class="font-semibold text-indigo-600 group-hover:text-indigo-700">
                                    Open
                                </span>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
