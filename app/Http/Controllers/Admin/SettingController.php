<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SettingRequest;
use App\Services\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function __construct(private readonly SettingService $settings) {}

    public function index(): View
    {
        return view('admin.settings.index', [
            'groups' => $this->settings->grouped(),
            'settings' => $this->settings,
        ]);
    }

    public function update(SettingRequest $request): RedirectResponse
    {
        $changed = $this->settings->save(
            $request->input('values', []),
            $request->file('files', []) ?: [],
            $request->input('remove', []),
        );

        // Tab yang sedang dibuka dikembalikan lewat fragment, supaya setelah
        // menyimpan pengguna tidak dilempar balik ke tab pertama.
        $tab = preg_replace('/[^a-z0-9_-]/', '', (string) $request->input('tab'));

        return redirect()->to(route('settings.index').($tab ? '#'.$tab : ''))
            ->with('success', $changed === 0
                ? 'Tidak ada perubahan yang perlu disimpan.'
                : "{$changed} pengaturan berhasil diperbarui.");
    }
}
