<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function index(): View
    {
        return view('admin.logs.index', [
            'users' => User::orderBy('name')->get(['id', 'name', 'username']),
            'events' => DB::table('activity_logs')->distinct()->orderBy('event')->pluck('event'),
            'tables' => DB::table('activity_logs')->whereNotNull('table_name')
                ->distinct()->orderBy('table_name')->pluck('table_name'),
        ]);
    }

    /** Endpoint server-side DataTables. */
    public function data(Request $request): JsonResponse
    {
        $query = ActivityLog::query()->with('user:id,name,username');

        $total = $query->clone()->count();

        foreach (['user_id' => 'user_id', 'event' => 'event', 'table_name' => 'table_name'] as $input => $column) {
            if ($request->filled($input)) {
                $query->where($column, $request->input($input));
            }
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        if ($search = $request->input('search.value')) {
            $query->where(function ($q) use ($search) {
                $q->where('table_name', 'like', "%{$search}%")
                    ->orWhere('record_id', 'like', "%{$search}%")
                    ->orWhere('module', 'like', "%{$search}%")
                    ->orWhere('url', 'like', "%{$search}%");
            });
        }

        $filtered = $query->clone()->count();

        // Log hanya diurutkan berdasarkan waktu; kolom lain tidak berguna
        // diurutkan dan membuka jalur injeksi tanpa manfaat.
        $direction = $request->input('order.0.dir') === 'asc' ? 'asc' : 'desc';

        $rows = $query->orderBy('created_at', $direction)
            ->orderBy('id', $direction)
            ->skip(max(0, (int) $request->input('start', 0)))
            ->take(min(max(1, (int) $request->input('length', 25)), 200))
            ->get();

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'data' => $rows->map(fn (ActivityLog $log) => [
                'id' => $log->id,
                'created_at' => $log->created_at?->format('d/m/Y H:i:s'),
                'user' => $log->user ? e($log->user->name) : '<span class="text-muted">sistem</span>',
                'event' => $log->event,
                'target' => e(trim(($log->table_name ?? '').($log->record_id ? ' #'.$log->record_id : ''))),
                'module' => e((string) $log->module),
                'ip_address' => e((string) $log->ip_address),
                'changed' => count($log->changedKeys()),
            ]),
        ]);
    }

    public function show(ActivityLog $log): View
    {
        return view('admin.logs.show', [
            'log' => $log->load('user:id,name,username,email'),
            'changed' => $log->changedKeys(),
        ]);
    }

    /**
     * Buang log yang lebih tua dari sekian hari.
     *
     * Tabel log tumbuh tanpa batas; tanpa pembersihan ia akan jadi tabel
     * terbesar di database dan memperlambat pencadangan.
     */
    public function prune(Request $request): RedirectResponse
    {
        $days = (int) $request->validate([
            'days' => ['required', 'integer', 'min:7', 'max:3650'],
        ])['days'];

        $cutoff = now()->subDays($days);
        $deleted = ActivityLog::where('created_at', '<', $cutoff)->delete();

        return back()->with('success',
            "{$deleted} baris log lebih tua dari {$days} hari dihapus.");
    }
}
