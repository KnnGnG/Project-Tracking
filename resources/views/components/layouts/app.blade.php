<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <link rel="stylesheet" href="{{ asset('css/fallback.css') }}">
    @endif

    <style>
        [x-cloak] { display: none !important; }
        body { font-family: Figtree, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        .app-scrollbar::-webkit-scrollbar { width: 10px; height: 10px; }
        .app-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .app-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 999px; border: 3px solid transparent; background-clip: content-box; }
        .app-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; border: 3px solid transparent; background-clip: content-box; }
        main .bg-white.border.border-gray-200,
        main .border.border-gray-200.bg-white {
            border-color: #e2e8f0 !important;
            box-shadow: 0 1px 2px rgb(15 23 42 / 0.04), 0 12px 30px -28px rgb(15 23 42 / 0.35);
        }
        main table thead { background: #f8fafc !important; }
        main table th { color: #64748b !important; font-size: 0.72rem; line-height: 1rem; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase; }
        main table tbody tr { transition: background-color 150ms ease; }
        main table tbody tr:hover { background: #f8fafc !important; }
        main input:not([type='checkbox']):not([type='radio']),
        main select,
        main textarea {
            border-color: #cbd5e1;
            color: #0f172a;
            box-shadow: 0 1px 1px rgb(15 23 42 / 0.02);
        }
        main input:not([type='checkbox']):not([type='radio']):focus,
        main select:focus,
        main textarea:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgb(99 102 241 / 0.14);
            outline: none;
        }
        main label { color: #334155; }
        .ui-page-heading { margin-bottom: 1.5rem; display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 1rem; }
        .ui-page-heading h2 { margin: 0; color: #0f172a; font-size: 1.25rem; line-height: 1.75rem; font-weight: 800; letter-spacing: 0; }
        .ui-page-heading p { margin-top: 0.35rem; color: #64748b; font-size: 0.875rem; line-height: 1.25rem; }
        .ui-soft-panel { border-radius: 1rem; border: 1px solid #e2e8f0; background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%); box-shadow: 0 1px 2px rgb(15 23 42 / 0.04), 0 18px 40px -32px rgb(15 23 42 / 0.45); }
        .ui-empty-state { border-radius: 1rem; border: 1px dashed #cbd5e1; background: #ffffff; padding: 4rem 1.5rem; text-align: center; color: #64748b; }
        button[disabled], .opacity-disabled { cursor: not-allowed; opacity: 0.65; }
        .ui-action-row { display: flex; align-items: center; gap: 0.75rem; }
        @media (max-width: 768px) {
            main .ui-soft-panel.overflow-hidden,
            main .bg-white.overflow-hidden,
            main .rounded-xl.overflow-hidden { overflow-x: auto; }
            main table { min-width: 720px; }
            .ui-page-heading { align-items: flex-start; }
        }
        .ui-action-button { border-radius: 0.5rem; border: 1px solid #e2e8f0; padding: 0.375rem 0.75rem; font-size: 0.75rem; line-height: 1rem; font-weight: 700; transition: all 150ms ease; }
        .ui-action-button:hover { background: #f8fafc; }
        .ui-action-danger { border-color: #fecaca; color: #dc2626; }
        .ui-action-danger:hover { background: #fef2f2; color: #b91c1c; }
        .ui-action-primary { border-color: #c7d2fe; color: #4f46e5; }
        .ui-action-primary:hover { background: #eef2ff; color: #4338ca; }

        .ui-table-scroll { overflow-x: auto; }
        main .ui-soft-panel table thead th,
        main .bg-white table thead th { position: sticky; top: 0; z-index: 2; }
        main td, main th { vertical-align: middle; }
        main span.rounded-full { display: inline-flex; align-items: center; gap: 0.25rem; white-space: nowrap; line-height: 1rem; }
        .ui-badge { display: inline-flex; align-items: center; border-radius: 9999px; padding: 0.125rem 0.625rem; font-size: 0.75rem; line-height: 1rem; font-weight: 700; white-space: nowrap; }
        .ui-form-section { border-radius: 0.875rem; border: 1px solid #e2e8f0; background: #fff; padding: 1rem; }
        .ui-loading-bar { position: fixed; inset: 0 0 auto 0; z-index: 80; height: 3px; overflow: hidden; background: transparent; }
        .ui-loading-bar::before { content: ''; display: block; height: 100%; width: 40%; background: linear-gradient(90deg, #6366f1, #22c55e); animation: ui-loading-slide 1s ease-in-out infinite; }
        @keyframes ui-loading-slide { 0% { transform: translateX(-110%); } 100% { transform: translateX(260%); } }
        @media (max-width: 1023px) {
            main table { min-width: 760px; }
            .ui-page-heading h2 { font-size: 1.125rem; line-height: 1.5rem; }
        }
    </style>

    @livewireStyles
</head>
<body class="bg-slate-100 font-sans antialiased text-slate-900">

<div wire:loading.delay class="ui-loading-bar" aria-hidden="true"></div>

<div
    x-data="{
        sidebarOpen: false,
        isDesktop: window.matchMedia('(min-width: 1024px)').matches,
        lastFocused: null,
        openSidebar() {
            this.lastFocused = document.activeElement;
            this.sidebarOpen = true;
            this.$nextTick(() => this.$refs.sidebar?.querySelector('a, button')?.focus());
        },
        closeSidebar() {
            this.sidebarOpen = false;
            this.$nextTick(() => this.lastFocused?.focus?.());
        }
    }"
    x-init="const desktopQuery = window.matchMedia('(min-width: 1024px)'); const syncDesktop = () => { isDesktop = desktopQuery.matches; if (isDesktop) sidebarOpen = false; }; desktopQuery.addEventListener('change', syncDesktop); syncDesktop();"
    class="flex h-screen overflow-hidden bg-slate-100"
>

    <div x-cloak x-show="sidebarOpen" x-transition.opacity @click="closeSidebar()" class="fixed inset-0 z-40 bg-slate-950/50 backdrop-blur-sm lg:hidden"></div>

    {{-- Sidebar --}}
    <aside
        x-ref="sidebar"
        :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
        :inert="! sidebarOpen && ! isDesktop"
        :aria-hidden="(! sidebarOpen && ! isDesktop).toString()"
        class="fixed inset-y-0 left-0 z-50 flex w-72 flex-shrink-0 flex-col border-r border-slate-800/80 bg-slate-950 text-white shadow-xl shadow-slate-950/10 transition-transform duration-200 ease-out lg:static lg:translate-x-0"
    >
        {{-- Logo --}}
        <div class="border-b border-white/10 px-5 py-5">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-3 rounded-xl px-1 py-1 transition hover:bg-white/5">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-500 text-sm font-extrabold text-white shadow-lg shadow-indigo-950/30">PT</div>
                <div class="min-w-0">
                    <p class="truncate text-base font-bold tracking-tight text-white">Project Tracker</p>
                    <p class="text-xs font-medium text-slate-400">Project workspace</p>
                </div>
            </a>
        </div>

        {{-- Navigation --}}
        <nav @click="if ($event.target.closest('a')) closeSidebar()" class="app-scrollbar flex-1 space-y-1 overflow-y-auto px-4 py-5">
            @auth
                @php
                    $authUser = auth()->user();
                    $isAdmin = $authUser->isAdmin();
                    $isClient = $authUser->isClient();
                    $activeTeamId = (int) (request()->integer('team') ?: session('active_team_id', 0));
                    $activeProjectId = (int) (request()->integer('project') ?: session('active_project_id', 0));
                    $activeProjectRole = match (true) {
                        request()->routeIs('lead.*') => 'lead',
                        request()->routeIs('member.*') => 'member',
                        default => session('active_project_role'),
                    };
                    $hasSelfAssignedTask = (bool) session('active_has_self_assigned_task', false);

                    $canLead = $authUser->isTeamLead();
                    $canMember = $authUser->isMember();
                    $showTeamLeadNav = $canLead
                        && ($activeProjectRole === 'lead' || ! $activeProjectRole);
                    $showMemberNav = ($canMember && ($activeProjectRole === 'member' || ! $activeProjectRole))
                        || ($canMember && $hasSelfAssignedTask && $showTeamLeadNav);
                    $activeMemberRouteParams = array_filter([
                        'team' => $activeTeamId ?: null,
                        'project' => $activeProjectId ?: null,
                    ]);
                    $activeLeadRouteParams = array_filter([
                        'team' => $activeTeamId ?: null,
                    ]);
                    $navActive = 'bg-indigo-500 text-white shadow-sm shadow-indigo-950/20';
                    $navIdle = 'text-slate-300 hover:bg-white/10 hover:text-white';
                    $navClass = 'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition';
                    $sectionClass = 'px-3 pb-1 pt-4 text-[11px] font-bold uppercase tracking-[0.16em] text-slate-500';
                @endphp

                @if($isAdmin)
                    <p class="{{ $sectionClass }}">Admin</p>
                    <a href="{{ route('admin.dashboard') }}" class="{{ $navClass }} {{ request()->routeIs('admin.dashboard') ? $navActive : $navIdle }}">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                        Dashboard
                    </a>
                    <a href="{{ route('admin.users') }}" class="{{ $navClass }} {{ request()->routeIs('admin.users') ? $navActive : $navIdle }}">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2h5M12 12a4 4 0 100-8 4 4 0 000 8z"/></svg>
                        Users
                    </a>
                    <a href="{{ route('admin.projects') }}" class="{{ $navClass }} {{ request()->routeIs('admin.projects') ? $navActive : $navIdle }}">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7h18M3 12h18M3 17h18"/></svg>
                        Projects
                    </a>
                    <a href="{{ route('admin.teams') }}" class="{{ $navClass }} {{ request()->routeIs('admin.teams') ? $navActive : $navIdle }}">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197"/></svg>
                        Teams
                    </a>
                    <a href="{{ route('admin.assign-teams') }}" class="{{ $navClass }} {{ request()->routeIs('admin.assign-teams') ? $navActive : $navIdle }}">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a4 4 0 00-4-4h-1M9 20H4v-2a4 4 0 014-4h1m6-6a3 3 0 11-6 0 3 3 0 016 0zm6 2a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        Assign Teams
                    </a>
                    <a href="{{ route('admin.tasks') }}" class="{{ $navClass }} {{ request()->routeIs('admin.tasks') ? $navActive : $navIdle }}">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        Task oversight
                    </a>
                @elseif($isClient)
                    <p class="{{ $sectionClass }}">Client</p>
                    <a href="{{ route('client.dashboard') }}" class="{{ $navClass }} {{ request()->routeIs('client.dashboard') ? $navActive : $navIdle }}">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        My Projects
                    </a>
                @else
                    <p class="{{ $sectionClass }}">Projects</p>
                    <a href="{{ route('projects.index') }}" class="{{ $navClass }} {{ request()->routeIs('projects.*') ? $navActive : $navIdle }}">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/></svg>
                        My Projects
                    </a>

                    @if($showTeamLeadNav)
                    <p class="{{ $sectionClass }}">Team Lead</p>
                    <a href="{{ route('lead.dashboard', $activeLeadRouteParams) }}" class="{{ $navClass }} {{ request()->routeIs('lead.dashboard') ? $navActive : $navIdle }}">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                        Dashboard
                    </a>
                    <a href="{{ route('lead.analytics', $activeLeadRouteParams) }}" class="{{ $navClass }} {{ request()->routeIs('lead.analytics') ? $navActive : $navIdle }}">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-6m3 10v-4m6 2V7M5 21h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                        Analytics
                    </a>
                    <a href="{{ route('lead.tasks', $activeLeadRouteParams) }}" class="{{ $navClass }} {{ request()->routeIs('lead.tasks') ? $navActive : $navIdle }}">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2m-6 9l2 2 4-4"/></svg>
                        Manage Tasks
                    </a>
                    <a href="{{ route('lead.journals', $activeLeadRouteParams) }}" class="{{ $navClass }} {{ request()->routeIs('lead.journals') ? $navActive : $navIdle }}">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6M7 4h10a2 2 0 012 2v14l-4-2-4 2-4-2-4 2V6a2 2 0 012-2z"/></svg>
                        Journal Review
                    </a>
                    <a href="{{ route('lead.evaluations', $activeLeadRouteParams) }}" class="{{ $navClass }} {{ request()->routeIs('lead.evaluations') ? $navActive : $navIdle }}">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.286 3.957a1 1 0 00.95.69h4.161c.969 0 1.371 1.24.588 1.81l-3.366 2.445a1 1 0 00-.364 1.118l1.286 3.957c.3.921-.755 1.688-1.539 1.118l-3.366-2.445a1 1 0 00-1.176 0l-3.366 2.445c-.784.57-1.838-.197-1.539-1.118l1.286-3.957a1 1 0 00-.364-1.118L4.062 9.384c-.783-.57-.38-1.81.588-1.81h4.161a1 1 0 00.95-.69l1.288-3.957z"/></svg>
                        Evaluation
                    </a>
                    @endif

                    @if($showMemberNav)
                    <p class="{{ $sectionClass }}">Member</p>
                    <a href="{{ route('member.dashboard', $activeMemberRouteParams) }}" class="{{ $navClass }} {{ request()->routeIs('member.dashboard') ? $navActive : $navIdle }}">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                        My Tasks
                    </a>
                    <a href="{{ route('member.logs', $activeMemberRouteParams) }}" class="{{ $navClass }} {{ request()->routeIs('member.logs') ? $navActive : $navIdle }}">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 6h8M8 10h8M8 14h5M5 4h14a1 1 0 011 1v14l-4-2-4 2-4-2-4 2V5a1 1 0 011-1z"/></svg>
                        Logs and Journal
                    </a>
                    <a href="{{ route('member.evaluations', $activeMemberRouteParams) }}" class="{{ $navClass }} {{ request()->routeIs('member.evaluations') ? $navActive : $navIdle }}">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.286 3.957a1 1 0 00.95.69h4.161c.969 0 1.371 1.24.588 1.81l-3.366 2.445a1 1 0 00-.364 1.118l1.286 3.957c.3.921-.755 1.688-1.539 1.118l-3.366-2.445a1 1 0 00-1.176 0l-3.366 2.445c-.784.57-1.838-.197-1.539-1.118l1.286-3.957a1 1 0 00-.364-1.118L4.062 9.384c-.783-.57-.38-1.81.588-1.81h4.161a1 1 0 00.95-.69l1.288-3.957z"/></svg>
                        My Evaluations
                    </a>
                    @endif
                @endif
            @endauth
        </nav>

        {{-- User info --}}
        @auth
        <div class="border-t border-white/10 px-4 py-4">
            @php
                $activeRoleLabel = match (true) {
                    request()->routeIs('admin.*') => 'Admin',
                    request()->routeIs('client.*') => 'Client',
                    request()->routeIs('lead.*') => 'Team Lead',
                    request()->routeIs('member.*') => 'Member',
                    default => null,
                };

                $contextRoles = collect();
                if ($isAdmin) { $contextRoles->push('Admin'); }
                if ($isClient) { $contextRoles->push('Client'); }
                if ($showTeamLeadNav) { $contextRoles->push('Team Lead'); }
                if ($showMemberNav) { $contextRoles->push('Member'); }
                $roleLabel = $activeRoleLabel ?? ($contextRoles->join(' / ') ?: $authUser->roleName());
            @endphp
            <div class="rounded-2xl border border-white/10 bg-white/[0.04] p-3">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-indigo-500 text-sm font-bold text-white">
                        {{ strtoupper(substr($authUser->name, 0, 1)) }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold text-white">{{ $authUser->name }}</p>
                        <p class="truncate text-xs font-medium text-slate-400">{{ $roleLabel }}</p>
                    </div>
                </div>
                <form method="POST" action="{{ route('logout') }}" class="mt-3">
                    @csrf
                    <button type="submit" class="w-full rounded-lg px-3 py-2 text-left text-xs font-semibold text-slate-400 transition hover:bg-white/10 hover:text-white">
                        Sign out
                    </button>
                </form>
            </div>
        </div>
        @endauth
    </aside>

    {{-- Main content --}}
    <main class="app-scrollbar min-w-0 flex-1 overflow-y-auto" :inert="sidebarOpen && ! isDesktop" :aria-hidden="(sidebarOpen && ! isDesktop).toString()" @keydown.escape.window="closeSidebar()">
        {{-- Top bar --}}
        <header class="sticky top-0 z-30 border-b border-slate-200/80 bg-white/95 px-6 py-4 shadow-sm shadow-slate-200/40 backdrop-blur lg:px-8">
            <div class="mx-auto flex max-w-[1600px] items-center justify-between gap-4">
                <div class="flex min-w-0 items-center gap-3">
                    <button type="button" @click="openSidebar()" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:bg-slate-50 lg:hidden" aria-label="Open navigation">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>
                    <div class="min-w-0">
                    <h1 class="truncate text-xl font-bold tracking-tight text-slate-950">{{ $title ?? 'Dashboard' }}</h1>
                    <p class="mt-0.5 hidden text-sm text-slate-500 sm:block">
                        Keep project work, teams, and progress in one clear workspace.
                    </p>
                    </div>
                </div>
                @auth
                    <div class="flex items-center gap-3">
                        <span class="hidden rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-600 md:inline-flex">
                            {{ $roleLabel ?? $authUser->roleName() }}
                        </span>
                        <livewire:notification-bell />
                    </div>
                @endauth
            </div>
        </header>

        <div class="mx-auto max-w-[1600px] px-6 py-6 lg:px-8 lg:py-8">
            {{ $slot }}
        </div>
    </main>

</div>

@livewireScripts
@stack('scripts')
</body>
</html>


