<?php

namespace App\Models;

use Database\Factories\TodoCommentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * A post in a todo's thread (Freelo-style comment) with optional file attachments.
 */
class TodoComment extends Model
{
    /** @use HasFactory<TodoCommentFactory> */
    use HasFactory;

    protected $guarded = [];

    /** Private disk the attachments live on; they are served only through the app. */
    public const DISK = 'local';

    public const MAX_FILE_KILOBYTES = 20 * 1024;

    protected static function booted(): void
    {
        // Row deletes cascade in the database, the files have to go explicitly.
        static::deleting(function (TodoComment $comment) {
            $comment->attachments->each->delete();
        });
    }

    /**
     * Directory holding the attachments of one todo's thread.
     */
    public static function directoryFor(int $todoId): string
    {
        return "todo-comments/{$todoId}";
    }

    /**
     * Removes the attachment files of the given todos; used when todos disappear through a cascade.
     *
     * @param  iterable<int>  $todoIds
     */
    public static function purgeFilesFor(iterable $todoIds): void
    {
        foreach ($todoIds as $todoId) {
            Storage::disk(self::DISK)->deleteDirectory(self::directoryFor($todoId));
        }
    }

    /**
     * The body is rendered as HTML. Plain text or Markdown (e.g. from the MCP tools) is
     * converted like a calculation description, and everything is sanitized before storing.
     */
    public function setBodyAttribute(?string $value): void
    {
        $html = Calculation::descriptionToHtml($value);
        $html = $html === null ? '' : self::sanitize($html);

        $this->attributes['body'] = self::isBlank($html) ? null : $html;
    }

    /**
     * An editor left untouched still sends markup like "<p><br></p>"; that is no text.
     */
    private static function isBlank(string $html): bool
    {
        $text = html_entity_decode(strip_tags($html, '<img><hr><table>'), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(str_replace("\u{00A0}", ' ', $text)) === '';
    }

    public static function sanitize(string $html): string
    {
        $config = (new HtmlSanitizerConfig)
            ->allowSafeElements()
            ->allowAttribute('class', ['li', 'ul', 'ol', 'code', 'pre'])
            ->allowLinkSchemes(['http', 'https', 'mailto', 'tel'])
            ->allowRelativeLinks()
            ->forceAttribute('a', 'rel', 'noopener noreferrer')
            ->forceAttribute('a', 'target', '_blank')
            ->withMaxInputLength(500_000);

        return trim((new HtmlSanitizer($config))->sanitize($html));
    }

    /**
     * @return BelongsTo<Todo, $this>
     */
    public function todo(): BelongsTo
    {
        return $this->belongsTo(Todo::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<TodoCommentAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(TodoCommentAttachment::class)->orderBy('id');
    }

    /**
     * Admins may manage any post, everyone else only their own.
     */
    public function isManageableBy(?User $user): bool
    {
        return $user !== null && ($user->isAdmin() || $this->user_id === $user->id);
    }
}
