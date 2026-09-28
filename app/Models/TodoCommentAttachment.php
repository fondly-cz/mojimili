<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
        $disk = Storage::disk(TodoComment::DISK);
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $path = TodoComment::directoryFor($comment->todo_id).'/'.Str::random(40).($extension !== '' ? '.'.$extension : '');

        $disk->put($path, $content);

        return $comment->attachments()->create([
            'path' => $path,
            'original_name' => $name,
            'caption' => $caption,
            'mime_type' => $disk->mimeType($path) ?: null,
            'size' => strlen($content),
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
