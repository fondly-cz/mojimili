<?php

namespace App\Mcp\Tools;

use App\Models\TodoComment;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;

#[Name('delete-todo-comment')]
#[Title('Smazat komentář k úkolu')]
#[Description('Smaže komentář k úkolu včetně všech jeho příloh. Smazat smí jen autor komentáře nebo administrátor.')]
class DeleteTodoCommentTool extends Tool
{
    use InteractsWithCrmUser;

    public function handle(Request $request): Response
    {
        if (! $user = $this->crmUser($request)) {
            return $this->accessDenied();
        }

        $validated = $request->validate([
            'id' => 'required|integer|exists:todo_comments,id',
        ], [
            'id.exists' => 'Komentář s tímto ID neexistuje. ID komentářů vrací get-project.',
        ]);

        $comment = TodoComment::with('todo')->findOrFail($validated['id']);

        if (! $comment->isManageableBy($user)) {
            return Response::error('Komentář může smazat jen jeho autor nebo administrátor.');
        }

        $comment->delete();

        return Response::text(sprintf('Komentář %d u úkolu "%s" byl smazán i s přílohami.', $comment->id, $comment->todo->name));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('ID mazaného komentáře.')
                ->required(),
        ];
    }
}
