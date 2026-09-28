<?php

namespace App\Mcp\Tools;

use App\Models\Todo;
use App\Models\TodoComment;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;

#[Name('create-todo-comment')]
#[Title('Přidat komentář k úkolu')]
#[Description('Přidá komentář k úkolu (jako ve Freelu), volitelně s přílohami v base64. Text může být HTML, Markdown i prostý text. Při přenosu z jiného systému lze uvést původní datum (created_at) a jméno autora bez účtu v CRM (author_name). ID úkolů zjistíš nástrojem get-project.')]
class CreateTodoCommentTool extends Tool
{
    use InteractsWithCrmUser;

    public function handle(Request $request): Response
    {
        if (! $user = $this->crmUser($request)) {
            return $this->accessDenied();
        }

        $validated = $request->validate([
            'todo_id' => 'required|integer|exists:todos,id',
            'body' => 'nullable|string|max:200000',
            'user_id' => 'nullable|integer|exists:users,id',
            'author_name' => 'nullable|string|max:255',
            'created_at' => 'nullable|date',
            'attachments' => 'nullable|array|max:20',
            'attachments.*.name' => 'required|string|max:255',
            'attachments.*.content_base64' => 'required|string',
        ], [
            'todo_id.exists' => 'Úkol s tímto ID neexistuje. ID úkolů zjistíš nástrojem get-project.',
            'user_id.exists' => 'Uživatel s tímto ID v CRM neexistuje.',
        ]);

        $files = [];

        foreach ($validated['attachments'] ?? [] as $attachment) {
            $content = base64_decode($attachment['content_base64'], true);

            if ($content === false) {
                return Response::error(sprintf('Příloha "%s" nemá platný obsah v base64.', $attachment['name']));
            }

            if (strlen($content) > TodoComment::MAX_FILE_KILOBYTES * 1024) {
                return Response::error(sprintf('Příloha "%s" je větší než %d MB.', $attachment['name'], TodoComment::MAX_FILE_KILOBYTES / 1024));
            }

            $files[] = ['name' => basename($attachment['name']), 'content' => $content];
        }

        $comment = new TodoComment(['body' => $validated['body'] ?? null]);

        if ($comment->body === null && $files === []) {
            return Response::error('Komentář musí obsahovat text nebo přílohu.');
        }

        $todo = Todo::with('todolist.project')->findOrFail($validated['todo_id']);
        // An imported author without a CRM account is shown by name only.
        $authorId = $validated['user_id'] ?? (empty($validated['author_name']) ? $user->id : null);

        DB::transaction(function () use ($comment, $todo, $authorId, $validated, $files) {
            $comment->todo_id = $todo->id;
            $comment->user_id = $authorId;
            $comment->author_name = $validated['author_name'] ?? null;

            if (! empty($validated['created_at'])) {
                $comment->created_at = $comment->updated_at = Carbon::parse($validated['created_at']);
            }

            $comment->save();

            $disk = Storage::disk(TodoComment::DISK);

            foreach ($files as $file) {
                $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $path = TodoComment::directoryFor($todo->id).'/'.Str::random(40).($extension !== '' ? '.'.$extension : '');
                $disk->put($path, $file['content']);

                $comment->attachments()->create([
                    'path' => $path,
                    'original_name' => $file['name'],
                    'mime_type' => $disk->mimeType($path) ?: null,
                    'size' => strlen($file['content']),
                ]);
            }
        });

        return Response::text(sprintf(
            "K úkolu \"%s\" byl přidán komentář (comment_id %d, autor %s, příloh: %d).\nDetail v CRM: %s",
            $todo->name,
            $comment->id,
            $comment->user?->name ?? $comment->author_name ?? 'neznámý',
            count($files),
            route('projects.show', $todo->todolist->project),
        ));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'todo_id' => $schema->integer()
                ->description('ID úkolu, ke kterému se komentář přidá.')
                ->required(),

            'body' => $schema->string()
                ->description('Text komentáře – HTML, Markdown nebo prostý text. Může chybět, pokud komentář nese jen přílohy.'),

            'user_id' => $schema->integer()
                ->description('ID uživatele CRM, který je autorem. Výchozí je přihlášený uživatel (neuvedeš-li author_name).'),

            'author_name' => $schema->string()
                ->description('Jméno autora, který v CRM nemá účet (např. při přenosu z Freela). Zobrazí se místo uživatele.'),

            'created_at' => $schema->string()
                ->description('Původní datum a čas komentáře ve formátu YYYY-MM-DD HH:MM. Výchozí je teď.'),

            'attachments' => $schema->array()
                ->description('Přílohy komentáře, každá nejvýš 20 MB.')
                ->items($schema->object([
                    'name' => $schema->string()->description('Název souboru včetně přípony.')->required(),
                    'content_base64' => $schema->string()->description('Obsah souboru v base64.')->required(),
                ])),
        ];
    }
}
