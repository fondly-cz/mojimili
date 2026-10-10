<?php

namespace App\Models;

use App\Models\Concerns\StoredAttachment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;

class TodoCommentAttachment extends Model
{
    use StoredAttachment;

    protected $guarded = [];

    /**
     * Stores an uploaded file next to the other files of the comment's todo.
     */
    public static function storeFor(TodoComment $comment, UploadedFile $file, ?string $caption = null): self
    {
        $path = $file->store(TodoComment::directoryFor($comment->todo_id), TodoComment::DISK);

        return $comment->attachments()->create([
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'caption' => $caption,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);
    }

    /**
     * Stores raw file content (e.g. a base64 attachment sent through MCP) like an upload.
     */
    public static function storeContent(TodoComment $comment, string $name, string $content, ?string $caption = null): self
    {
        return $comment->attachments()->create(self::putContent($comment->todo_id, $name, $content, $caption));
    }

    /**
     * @return BelongsTo<TodoComment, $this>
     */
    public function comment(): BelongsTo
    {
        return $this->belongsTo(TodoComment::class, 'todo_comment_id');
    }

    protected function routeName(): string
    {
        return 'todo-comment-attachments.show';
    }
}
