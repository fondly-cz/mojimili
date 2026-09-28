<?php

namespace App\Http\Controllers;

use App\Models\Label;
use Illuminate\Http\Request;

class LabelController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate(Label::rules());

        Label::create([...$validated, 'color' => $validated['color'] ?? Label::DEFAULT_COLOR]);

        return back()->with('success', 'Štítek byl vytvořen.');
    }

    public function update(Request $request, Label $label)
    {
        $validated = $request->validate(Label::rules($label));

        $label->update([...$validated, 'color' => $validated['color'] ?? $label->color]);

        return back()->with('success', 'Štítek byl upraven.');
    }

    public function destroy(Label $label)
    {
        $label->delete();

        return back()->with('success', 'Štítek byl smazán.');
    }
}
