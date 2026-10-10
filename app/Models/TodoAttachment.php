<?php

namespace App\Models;

use App\Models\Concerns\StoredAttachment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;

/**
 * A file attached to a todo's description.
 */
class TodoAttachment extends Model
{
    use StoredAttachment;

    protected $guarded = [];

    /**
     * Stores an uploaded file next to the comment files of the todo, so removing the todo purges both.
     */
    public static function storeFor(Todo $todo, UploadedFile $file, ?string $caption = null): self
    {
        return $todo->attachments()->create([
            'path' => $file->store(TodoComment::directoryFor($todo->id), TodoComment::DISK),
            'original_name' => $file->getClientOriginalName(),
            'caption' => $caption,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);
    }

    /**
     * Stores raw file content (e.g. an attachment sent through MCP) like an upload.
     */
    public static function storeContent(Todo $todo, string $name, string $content, ?string $caption = null): self
    {
        return $todo->attachments()->create(self::putContent($todo->id, $name, $content, $caption));
    }

    /**
     * @return BelongsTo<Todo, $this>
     */
    public function todo(): BelongsTo
    {
        return $this->belongsTo(Todo::class);
    }

    protected function routeName(): string
    {
        return 'todo-attachments.show';
    }
}
