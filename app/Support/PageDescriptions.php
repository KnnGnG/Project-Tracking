<?php

namespace App\Support;

use Illuminate\Http\Request;

class PageDescriptions
{
    public static function forCurrentRoute(?Request $request = null): string
    {
        $request ??= request();

        return match (true) {
            $request->routeIs('admin.dashboard') => 'Review system activity, project health, and overall workload.',
            $request->routeIs('admin.users') => 'Manage accounts, roles, and team access from one place.',
            $request->routeIs('admin.projects') => 'Create projects, connect teams, and keep delivery timelines organized.',
            $request->routeIs('admin.teams') => 'Create teams, assign leads, and keep member responsibilities clear.',
            $request->routeIs('admin.assign-teams') => 'Build reusable teams now and attach them to projects later.',
            $request->routeIs('admin.tasks') => 'View task status and workload across all projects.',
            $request->routeIs('lead.dashboard') => 'Monitor timeline progress, team workload, and urgent project activity.',
            $request->routeIs('lead.analytics') => 'Track completion, punctuality, and logged hours for the selected team.',
            $request->routeIs('lead.tasks') => 'Assign work, review member progress, and keep task ownership clear.',
            $request->routeIs('lead.journals') => 'Review team logs, general work, and task-specific time entries.',
            $request->routeIs('lead.evaluations') => 'Evaluate members and keep feedback visible and actionable.',
            $request->routeIs('member.dashboard') => 'Focus on assigned work, due dates, and personal task progress.',
            $request->routeIs('member.logs') => 'Record daily work, timer sessions, and general team activity.',
            $request->routeIs('member.evaluations') => 'Review feedback from your team leads.',
            $request->routeIs('client.dashboard') => 'Follow project progress, milestones, and delivery updates.',
            $request->routeIs('projects.*') => 'Choose a project to open the dashboard for your role.',
            default => 'Keep project work, teams, and progress in one clear workspace.',
        };
    }
}