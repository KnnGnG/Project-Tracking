<?php

use App\Http\Controllers\DashboardController;
use App\Livewire\Admin\AdminDashboard;
use App\Livewire\Admin\ProjectManager;
use App\Livewire\Admin\TaskAssigner;
use App\Livewire\Admin\TeamManager;
use App\Livewire\Admin\UserManager;
use App\Livewire\Client\ClientDashboard;
use App\Livewire\Lead\LeadTaskManager;
use App\Livewire\Lead\TeamLeadAnalytics;
use App\Livewire\Lead\TeamLeadDashboard;
use App\Livewire\Member\MemberDashboard;
use App\Livewire\Member\MemberJournal;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public routes
|--------------------------------------------------------------------------
| Redirect root to login for guests, or to their dashboard if signed in.
*/

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('home');

/*
|--------------------------------------------------------------------------
| Authenticated routes  (any logged-in user, any role)
|--------------------------------------------------------------------------
| throttle:300,1  →  max 300 requests per minute per user/IP
*/

Route::middleware(['auth', 'throttle:300,1'])->group(function () {

    // Role-based dashboard redirect
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    /*
    |----------------------------------------------------------------------
    | Admin
    |----------------------------------------------------------------------
    */
    Route::middleware(['role:admin'])
        ->prefix('admin')
        ->name('admin.')
        ->group(function () {
            Route::get('/dashboard', AdminDashboard::class)->name('dashboard');
            Route::get('/users', UserManager::class)->name('users');
            Route::get('/projects', ProjectManager::class)->name('projects');
            Route::get('/teams', TeamManager::class)->name('teams');
            Route::get('/tasks', TaskAssigner::class)->name('tasks');
        });

    /*
    |----------------------------------------------------------------------
    | Client
    |----------------------------------------------------------------------
    */
    Route::middleware(['role:client'])
        ->prefix('client')
        ->name('client.')
        ->group(function () {
            Route::get('/dashboard', ClientDashboard::class)->name('dashboard');
        });

    /*
    |----------------------------------------------------------------------
    | Team Lead
    |----------------------------------------------------------------------
    */
    Route::middleware(['role:team_lead'])
        ->prefix('lead')
        ->name('lead.')
        ->group(function () {
            Route::get('/dashboard', TeamLeadDashboard::class)->name('dashboard');
            Route::get('/analytics', TeamLeadAnalytics::class)->name('analytics');
            Route::get('/tasks', LeadTaskManager::class)->name('tasks');
        });

    /*
    |----------------------------------------------------------------------
    | Member
    |----------------------------------------------------------------------
    */
    Route::middleware(['role:member'])
        ->prefix('member')
        ->name('member.')
        ->group(function () {
            Route::get('/dashboard', MemberDashboard::class)->name('dashboard');
            Route::get('/logs', MemberJournal::class)->name('logs');
        });
});
