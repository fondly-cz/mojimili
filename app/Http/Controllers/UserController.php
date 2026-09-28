<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $users = User::query()
            ->when($request->search, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($request->role, function ($query, $role) {
                $role === 'none'
                    ? $query->whereNull('role')
                    : $query->where('role', $role);
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Users/Index', [
            'users' => $users,
            'filters' => $request->only(['search', 'role']),
            'roles' => collect(UserRole::cases())
                ->map(fn (UserRole $role) => ['value' => $role->value, 'label' => $role->label()])
                ->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            ...$this->rules(),
            'email' => 'required|email|max:255|unique:users,email',
        ]);

        User::create([
            ...$validated,
            // Without a password the user signs in through Google only.
            'password' => $validated['password'] ?? Hash::make(Str::random(40)),
        ])->markEmailAsVerified();

        return back()->with('success', 'Uživatel byl úspěšně vytvořen.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            ...$this->rules(),
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        // An admin demoting themselves could leave the CRM without anyone to manage it.
        if ($user->is($request->user()) && ($validated['role'] ?? null) !== UserRole::ADMIN->value) {
            return back()->withErrors(['role' => 'Svou vlastní administrátorskou roli odebrat nemůžete.']);
        }

        if (empty($validated['password'])) {
            unset($validated['password']);
        }

        $user->update($validated);

        return back()->with('success', 'Uživatel byl úspěšně upraven.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->withErrors(['user' => 'Svůj vlastní účet smazat nemůžete.']);
        }

        $user->delete();

        return back()->with('success', 'Uživatel byl úspěšně smazán.');
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'company' => 'nullable|string|max:255',
            'role' => ['nullable', Rule::enum(UserRole::class)],
            'password' => 'nullable|string|min:8',
        ];
    }
}
