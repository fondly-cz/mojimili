<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class TodoCommentAttachment extends Model
{
    protected $guarded = [];

    protected $hidden = ['path'];

    protected $casts = [
        'size' => 'integer',
    ];

    protected $appends = ['url', 'is_image'];

    protected static function booted(): void
    {
        static::deleted(function (TodoCommentAttachment $attachment) {
            Storage::disk(TodoComment::DISK)->delete($attachment->path);
        });
    }

    /**
     * Stores an uploaded file next to the other files of the comment's todo.
     */
    public static function storeFor(TodoComment $comment, UploadedFile $file): self
    {
        $path = $file->store(TodoComment::directoryFor($comment->todo_id), TodoComment::DISK);

        return $comment->attachments()->create([
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);
    }

    /**
     * @return BelongsTo<TodoComment, $this>
     */
    public function comment(): BelongsTo
    {
        return $this->belongsTo(TodoComment::class, 'todo_comment_id');
    }

    public function getUrlAttribute(): string
    {
        return route('todo-comment-attachments.show', $this);
    }

    public function getIsImageAttribute(): bool
    {
        return in_array($this->mime_type, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true);
    }
}
