<?php

namespace App\Mcp\Tools;

use App\Models\TodoComment;
use App\Models\TodoCommentAttachment;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;

#[Name('update-todo-comment')]
#[Title('Upravit komentář k úkolu')]
#[Description('Upraví komentář k úkolu – změní text, přidá další přílohy (obrázky i jiné soubory přes url ke stažení nebo upload_id z create-upload-link) nebo odebere stávající. Upravit smí jen autor komentáře nebo administrátor. ID komentářů a příloh vrací get-project.')]
class UpdateTodoCommentTool extends Tool
{
    use DecodesCommentAttachments, InteractsWithCrmUser;

    public function handle(Request $request): Response
    {
        if (! $user = $this->crmUser($request)) {
            return $this->accessDenied();
        }

        $validated = $request->validate([
            'id' => 'required|integer|exists:todo_comments,id',
            'body' => 'sometimes|nullable|string|max:200000',
            'remove_attachment_ids' => 'nullable|array',
            'remove_attachment_ids.*' => 'integer',
            ...$this->attachmentRules(),
        ], [
            'id.exists' => 'Komentář s tímto ID neexistuje. ID komentářů vrací get-project.',
        ]);

        $comment = TodoComment::with('attachments', 'todo')->findOrFail($validated['id']);

        if (! $comment->isManageableBy($user)) {
            return Response::error('Komentář může upravit jen jeho autor nebo administrátor.');
        }

        [$files, $error] = $this->decodeAttachments($validated['attachments'] ?? [], $user);

        if ($error) {
            return Response::error($error);
        }

        if (array_key_exists('body', $validated)) {
            $comment->body = $validated['body'];
        }

        $removeIds = $validated['remove_attachment_ids'] ?? [];
        $kept = $comment->attachments->whereNotIn('id', $removeIds)->count();

        if ($comment->body === null && $kept === 0 && $files === []) {
            return Response::error('Komentář musí obsahovat text nebo přílohu.');
        }

        DB::transaction(function () use ($comment, $removeIds, $files) {
            $comment->save();

            $comment->attachments()->whereIn('id', $removeIds)->get()->each->delete();

            foreach ($files as $file) {
                TodoCommentAttachment::storeContent($comment, $file['name'], $file['content'], $file['caption']);
            }
        });

        return Response::text(sprintf(
            "Komentář %d u úkolu \"%s\" byl upraven (příloh: %d).\nDetail v CRM: %s",
            $comment->id,
            $comment->todo->name,
            $comment->attachments()->count(),
            route('todos.show', $comment->todo),
        ));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('ID upravovaného komentáře.')
                ->required(),

            'body' => $schema->string()
                ->description('Nový text komentáře – HTML, Markdown nebo prostý text. Vynech, má-li zůstat beze změny.'),

            'attachments' => $this->attachmentsSchema($schema),

            'remove_attachment_ids' => $schema->array()
                ->description('ID příloh, které se mají z komentáře odebrat.')
                ->items($schema->integer()),
        ];
    }
}
