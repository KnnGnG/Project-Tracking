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
                            $statusClass = match($project->status) {
                                'active' => 'bg-green-100 text-green-700',
                                'on_hold' => 'bg-yellow-100 text-yellow-700',
                                'completed' => 'bg-blue-100 text-blue-700',
                                default => 'bg-gray-100 text-gray-600',
                            };
                        @endphp

                        <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <h3 class="truncate text-lg font-semibold text-gray-900">
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

                            <div class="mt-5 space-y-2">
                                @foreach($item['teams'] as $team)
                                    @php
                                        $teamRole = $team->pivot->role ?? 'member';
                                        $roleLabel = $teamRole === 'lead' ? 'Team Lead' : 'Member';
                                        $roleClass = $teamRole === 'lead'
                                            ? 'bg-indigo-50 text-indigo-700 border-indigo-100'
                                            : 'bg-emerald-50 text-emerald-700 border-emerald-100';
                                    @endphp
                                    <a href="{{ route('projects.open', ['project' => $project, 'team' => $team->id]) }}"
                                       class="group flex items-center justify-between gap-3 rounded-lg border border-gray-100 bg-gray-50 px-3 py-2 transition hover:border-indigo-300 hover:bg-white hover:shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-semibold text-gray-800 group-hover:text-indigo-700">
                                                {{ $team->name }}
                                            </p>
                                            <span class="mt-1 inline-flex rounded-full border px-2 py-0.5 text-[11px] font-semibold {{ $roleClass }}">
                                                Opens as {{ $roleLabel }}
                                            </span>
                                        </div>
                                        <span class="shrink-0 text-sm font-semibold text-indigo-600 group-hover:text-indigo-700">
                                            Open
                                        </span>
                                    </a>
                                @endforeach
                            </div>

                            <div class="mt-5 border-t border-gray-100 pt-4 text-sm">
                                <span class="text-gray-500">
                                    @if($project->start_date || $project->end_date)
                                        {{ $project->start_date?->format('M d, Y') ?? 'TBD' }}
                                        @if($project->end_date)
                                            - {{ $project->end_date->format('M d, Y') }}
                                        @endif
                                    @else
                                        Dates not set
                                    @endif
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
