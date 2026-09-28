<?php

namespace App\Mcp\Tools;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;

#[Name('create-user')]
#[Title('Založit uživatele')]
#[Description('Založí nového uživatele CRM, např. kolegu nebo externistu, kterému se pak přiřazují úkoly, vykazuje čas nebo píší komentáře. Bez role se uživatel do CRM nepřihlásí – je jen osobou pro výkazy a komentáře. Roli (admin, manager) smí přidělit pouze administrátor. Uživatel se přihlašuje přes Google účtem se stejným e-mailem.')]
class CreateUserTool extends Tool
{
    use InteractsWithCrmUser;

    public function handle(Request $request): Response
    {
        if (! $user = $this->crmUser($request)) {
            return $this->accessDenied();
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'phone' => 'nullable|string|max:50',
            'company' => 'nullable|string|max:255',
            'role' => ['nullable', Rule::enum(UserRole::class)],
        ], [
            'email.unique' => 'Uživatel s tímto e-mailem už v CRM existuje. Najdeš ho nástrojem list-users.',
        ]);

        // Access to the CRM is granted only by an admin, as in the web UI.
        if (! empty($validated['role']) && ! $user->isAdmin()) {
            return Response::error('Roli může uživateli přidělit pouze administrátor. Založ uživatele bez role.');
        }

        $created = User::create([
            ...$validated,
            // Sign-in goes through Google; nobody knows this password.
            'password' => Hash::make(Str::random(40)),
            'role' => $validated['role'] ?? null,
        ]);

        return Response::text(sprintf(
            'Uživatel "%s" <%s> byl založen (user_id %d, role: %s). Jeho ID použiješ jako user_id u výkazů a komentářů nebo assigned_user_id u úkolů.',
            $created->name,
            $created->email,
            $created->id,
            $created->role?->label() ?? 'bez přístupu do CRM',
        ));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()
                ->description('Celé jméno uživatele.')
                ->required(),

            'email' => $schema->string()
                ->description('E-mail uživatele; musí být v CRM jedinečný. Stejným Google účtem se pak přihlásí.')
                ->required(),

            'phone' => $schema->string()
                ->description('Telefon.'),

            'company' => $schema->string()
                ->description('Firma, pro kterou uživatel pracuje.'),

            'role' => $schema->string()
                ->enum(array_column(UserRole::cases(), 'value'))
                ->description('Role v CRM: admin nebo manager. Vynech pro uživatele bez přístupu do CRM. Přidělit ji smí jen administrátor.'),
        ];
    }
}
