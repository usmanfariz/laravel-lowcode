<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $widgets = $this->dashboard->widgetsFor($user);

        return view('dashboard.index', [
            'widgets' => $widgets,
            // Isi tiap widget dihitung di sini, dikunci per id, supaya view
            // tidak memanggil query sendiri di tengah perulangan.
            'data' => $widgets->mapWithKeys(fn ($w) => [$w->id => $this->dashboard->resolve($w, $user)]),
            'dashboard' => $this->dashboard,
        ]);
    }
}
