<?php

namespace App\Mcp\Tools;

use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;

#[Name('list-users')]
#[Title('Seznam uživatelů')]
#[Description('Vypíše uživatele CRM. Jejich ID použiješ jako user_id u výkazů práce, assigned_user_id u úkolů nebo v sazbách osob v projektu.')]
class ListUsersTool extends Tool
{
    use InteractsWithCrmUser;

    public function handle(Request $request): Response
    {
        if (! $this->crmUser($request)) {
            return $this->accessDenied();
        }

        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
        ]);

        $users = User::query()
            ->when($validated['search'] ?? null, fn ($query, $search) => $query->where(
                fn ($q) => $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
            ))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        if ($users->isEmpty()) {
            return Response::text('Nenalezen žádný uživatel odpovídající zadání.');
        }

        return Response::text($users->map(fn (User $user) => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ])->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()
                ->description('Hledaný výraz ve jménu nebo e-mailu uživatele.'),
        ];
    }
}
