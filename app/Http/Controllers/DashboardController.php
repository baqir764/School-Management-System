<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\DashboardStatsService;
use App\Services\ParentOverviewService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(DashboardStatsService $stats): View
    {
        return view('dashboard.index', [
            'cards' => $stats->cards(),
            'usersByRole' => $stats->usersByRole(),
            // Only the widgets the dashboard view actually renders are
            // computed - the previous recentAdmissions/recentPayments/
            // recentAnnouncements queries ran on every dashboard load but
            // no view ever read them (removed in the 2026-11 performance
            // pass).
            'recentActivity' => AuditLog::query()->latest('created_at')->limit(8)->get(),
            // Null for staff/admins and users without linked children -
            // the view renders the parent section only when present.
            'parentOverview' => ParentOverviewService::forUser(auth()->user()),
        ]);
    }
}
