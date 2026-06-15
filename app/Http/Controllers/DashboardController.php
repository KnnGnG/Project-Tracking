<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        if ($request->user()->teams()->exists() && ! $request->user()->isAdmin() && ! $request->user()->isClient()) {
            return redirect()->route('projects.index');
        }

        return redirect()->route($request->user()->dashboardRoute());
    }
}
