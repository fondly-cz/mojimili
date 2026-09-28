<?php

namespace App\Http\Controllers;

use App\Models\Todo;
use App\Models\TodoComment;
use App\Models\TodoCommentAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class TodoCommentController extends Controller
{
    public function store(Request $request, Todo $todo)
    {
        $validated = $request->validate($this->rules());
        $files = $request->file('files', []);

        $comment = new TodoComment(['body' => $validated['body'] ?? null]);

        if ($comment->body === null && $files === []) {
            throw ValidationException::withMessages(['body' => 'Komentář musí obsahovat text nebo přílohu.']);
        }

        DB::transaction(function () use ($todo, $comment, $request, $files) {
            $comment->todo()->associate($todo);
            $comment->user()->associate($request->user());
            $comment->save();

            foreach ($files as $file) {
                TodoCommentAttachment::storeFor($comment, $file);
            }
        });

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
    public function attachment(TodoCommentAttachment $attachment)
    {
        $disk = Storage::disk(TodoComment::DISK);

        abort_unless($disk->exists($attachment->path), 404);

        $inline = $attachment->is_image || $attachment->mime_type === 'application/pdf';

        return $disk->response($attachment->path, $attachment->original_name, [
            'Content-Type' => $attachment->mime_type ?? 'application/octet-stream',
            // Uploaded files must never run as HTML/JS in the app's origin.
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => 'sandbox',
        ], $inline ? 'inline' : 'attachment');
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
