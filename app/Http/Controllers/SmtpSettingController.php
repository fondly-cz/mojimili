<?php

namespace App\Http\Controllers;

use App\Mail\TestEmail;
use App\Models\SmtpSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SmtpSettingController extends Controller
{
    public function edit()
    {
        $smtp = SmtpSetting::first();
        $mailer = config('mail.default');

        return Inertia::render('Settings/Email', [
            'smtp' => $smtp ? [
                'enabled' => $smtp->enabled,
                'host' => $smtp->host,
                'port' => $smtp->port,
                'encryption' => $smtp->encryption,
                'username' => $smtp->username,
                'passwordConfigured' => filled($smtp->password),
                'from_address' => $smtp->from_address,
                'from_name' => $smtp->from_name,
            ] : null,
            // Efektivní konfigurace (po případném přebití z administrace), bez hesla.
            'effective' => [
                'mailer' => $mailer,
                'host' => config("mail.mailers.$mailer.host"),
                'port' => config("mail.mailers.$mailer.port"),
                'username' => config("mail.mailers.$mailer.username"),
                'fromAddress' => config('mail.from.address'),
                'fromName' => config('mail.from.name'),
                'source' => $smtp?->enabled ? 'admin' : 'env',
            ],
        ]);
    }

    public function update(Request $request)
    {
        $existing = SmtpSetting::first();

        $validated = $request->validate([
            'enabled' => 'required|boolean',
            'host' => 'required|string|max:255',
            'port' => 'required|integer|between:1,65535',
            'encryption' => ['nullable', Rule::in(['tls', 'ssl'])],
            'username' => 'nullable|string|max:255',
            'password' => 'nullable|string|max:255',
            'from_address' => 'required|email|max:255',
            'from_name' => 'nullable|string|max:255',
        ]);

        // Prázdné heslo = ponechat stávající (heslo se do formuláře nevrací).
        if (blank($validated['password'] ?? null)) {
            unset($validated['password']);
        }

        if ($existing) {
            $existing->update($validated);
        } else {
            SmtpSetting::create($validated);
        }

        return redirect()->route('smtp-settings.edit')->with('success', 'Nastavení SMTP serveru bylo uloženo.');
    }

    public function destroy()
    {
        SmtpSetting::query()->delete();

        return redirect()->route('smtp-settings.edit')
            ->with('success', 'Vlastní SMTP server byl smazán, používá se nastavení z .env.');
    }

    public function test(Request $request): JsonResponse
    {
        $recipient = $request->validate(['email' => 'required|email|max:255'])['email'];

        try {
            Mail::to($recipient)->send(new TestEmail);
            Mail::to($recipient)->queue(new TestEmail(viaQueue: true));
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Odeslání selhalo: '.$e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Odeslány dva testy na '.$recipient.': přímý a přes frontu. Pokud dorazí jen jeden, neběží queue worker – a ostatní e-maily z aplikace se neodešlou.',
        ]);
    }
}
