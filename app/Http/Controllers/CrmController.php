<?php

namespace App\Http\Controllers;

use App\Models\Calculation;
use App\Models\Company;
use App\Models\Service;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CrmController extends Controller
{
    public function welcome(Request $request)
    {
        // The overview is about companies and prices; the other roles start in their projects.
        if (! $request->user()->isStaff()) {
            return redirect()->route('projects.index');
        }

        return Inertia::render('Dashboard', [
            'stats' => [
                'companies' => Company::count(),
                'calculations' => Calculation::count(),
                'services' => Service::count(),
            ],
        ]);
    }
}
