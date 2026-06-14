<?php

namespace App\Http\Controllers;

use App\Services\SatuSehatDashboardStatistics;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Throwable;

class DashboardController extends Controller
{
    public function index(Request $request, SatuSehatDashboardStatistics $statistics)
    {
        $filters = $request->validate([
            'period' => ['nullable', 'in:7,30,90,365,custom'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $period = $filters['period'] ?? '30';
        $to = $filters['to'] ?? now()->toDateString();
        $from = $period === 'custom'
            ? ($filters['from'] ?? now()->subDays(29)->toDateString())
            : Carbon::parse($to)->subDays(((int) $period) - 1)->toDateString();
        $dashboard = ['items' => collect(), 'totals' => ['all' => 0, 'sent' => 0, 'pending' => 0]];
        $connectionError = null;
        try {
            $dashboard = $statistics->get($from, $to);
            $dashboard['items'] = $dashboard['items']->filter(fn (array $item) => auth()->user()->isSuperAdmin() || auth()->user()->hasPermission($item['permission']))->values();
            $dashboard['totals'] = [
                'all' => $dashboard['items']->where('available', true)->sum('all'),
                'sent' => $dashboard['items']->where('available', true)->sum('sent_count'),
                'pending' => $dashboard['items']->where('available', true)->sum('pending'),
            ];
        } catch (Throwable) {
            $connectionError = 'Database eksternal belum dapat diakses. Periksa konfigurasi database SIMRS.';
        }

        return view('pages.dashboard.ecommerce', [
            'title' => 'Dashboard', 'dashboard' => $dashboard, 'connectionError' => $connectionError,
            'filters' => compact('period', 'from', 'to'),
        ]);
    }
}
