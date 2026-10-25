<?php

namespace App\Http\Controllers;

use App\Models\Todo;
use App\Models\TodoComment;
use App\Models\TodoCommentAttachment;
use App\Notifications\TodoActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TodoCommentController extends Controller
{
    public function store(Request $request, Todo $todo)
    {
        $project = $todo->todolist->project;

        $validated = $request->validate([
            ...$this->rules(),
            // Optional extras sent along with the post: hand the todo over and/or report time.
            'assigned_user_id' => ['sometimes', 'nullable', $project->memberRule()],
            'report_minutes' => 'sometimes|nullable|integer|min:1|max:1440',
        ], ['assigned_user_id.exists' => 'Řešitelem může být jen osoba přiřazená k projektu.']);

        $user = $request->user();
        $reportMinutes = $validated['report_minutes'] ?? null;
        $changeAssignee = $request->has('assigned_user_id') && ! $user->isCustomer();

        abort_if($reportMinutes && ! $user->canReportTime(), 403);

        $files = $request->file('files', []);

        $comment = new TodoComment(['body' => $validated['body'] ?? null]);

        if ($comment->body === null && $files === []) {
            throw ValidationException::withMessages(['body' => 'Komentář musí obsahovat text nebo přílohu.']);
        }

        DB::transaction(function () use ($todo, $comment, $files, $project, $reportMinutes, $changeAssignee, $validated, $user) {
            $comment->todo()->associate($todo);
            $comment->user()->associate($user);
            $comment->save();

            foreach ($files as $file) {
                TodoCommentAttachment::storeFor($comment, $file);
            }

            if ($reportMinutes) {
                $todo->workReports()->create([
                    'user_id' => $user->id,
                    'date' => now()->toDateString(),
                    'minutes' => $reportMinutes,
                    'hourly_rate' => $project->rateFor($user->id),
                ]);
            }

            if ($changeAssignee) {
                $todo->update(['assigned_user_id' => $validated['assigned_user_id'] ?? null]);
            }
        });

        if ($changeAssignee && $todo->wasChanged('assigned_user_id')) {
            TodoController::notifyAssignee($todo, $user);
        }

        TodoController::notifyWatchers($todo, $user, TodoActivity::COMMENTED);

        return back()->with('success', 'Komentář byl přidán.');
    }

    public function update(Request $request, TodoComment $comment)
    {
        abort_unless($comment->isManageableBy($request->user()), 403);

        $validated = $request->validate([
            ...$this->rules(),
            'remove_attachment_ids' => 'nullable|array',
            'remove_attachment_ids.*' => 'integer',
        ]);
        $files = $request->file('files', []);
        $removeIds = $validated['remove_attachment_ids'] ?? [];

        $comment->body = $validated['body'] ?? null;
        $keptAttachments = $comment->attachments->whereNotIn('id', $removeIds)->count();

        if ($comment->body === null && $keptAttachments === 0 && $files === []) {
            throw ValidationException::withMessages(['body' => 'Komentář musí obsahovat text nebo přílohu.']);
        }

        DB::transaction(function () use ($comment, $removeIds, $files) {
            $comment->save();

            $comment->attachments()->whereIn('id', $removeIds)->get()->each->delete();

            foreach ($files as $file) {
                TodoCommentAttachment::storeFor($comment, $file);
            }
        });

        return back()->with('success', 'Komentář byl upraven.');
    }

    public function destroy(Request $request, TodoComment $comment)
    {
        abort_unless($comment->isManageableBy($request->user()), 403);

        $comment->delete();

        return back()->with('success', 'Komentář byl smazán.');
    }

    /**
     * Serves an attachment from the private disk; images and PDFs open in the browser.
     */
    public function attachment(Request $request, TodoCommentAttachment $attachment)
    {
        return $attachment->toResponse($request->boolean('download'));
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'body' => 'nullable|string|max:200000',
            'files' => 'nullable|array|max:20',
            'files.*' => 'file|max:'.TodoComment::MAX_FILE_KILOBYTES,
        ];
    }
}
