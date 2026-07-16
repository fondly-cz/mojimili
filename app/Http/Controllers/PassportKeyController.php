<?php

namespace App\Http\Controllers;

use App\Actions\GeneratePassportKeys;
use App\Models\PassportKey;
use Inertia\Inertia;

class PassportKeyController extends Controller
{
    public function edit()
    {
        $keys = PassportKey::first();

        return Inertia::render('Settings/PassportKeys', [
            'hasKeys' => (bool) $keys,
            'generatedAt' => $keys?->updated_at?->toIso8601String(),
            'mcpUrl' => rtrim(config('app.url'), '/').'/mcp',
        ]);
    }

    public function regenerate(GeneratePassportKeys $generator)
    {
        $generator->generate();

        return redirect()
            ->route('passport-keys.edit')
            ->with('success', 'OAuth klíče pro MCP server byly vygenerovány. Připojení klienti se musí znovu přihlásit.');
    }
}
