<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        if ($request->user()?->hasRole('ADMINISTRADOR')) {
            return redirect()->route('admin.dashboard');
        }

        if ($request->user()?->hasRole('VIGILANTE')) {
            return redirect()->route('vigilancia.index');
        }

        return redirect()->route('papeletas.index');
    }
}
