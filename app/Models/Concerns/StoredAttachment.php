<?php

namespace App\Models\Concerns;

use App\Models\TodoComment;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A file kept on the private disk and served only through the app (todo and comment attachments).
 */
trait StoredAttachment
{
    private const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    public static function bootStoredAttachment(): void
    {
        static::deleted(function (self $attachment) {
            Storage::disk(TodoComment::DISK)->delete($attachment->path);
        });
    }

    public function initializeStoredAttachment(): void
    {
        $this->mergeCasts(['size' => 'integer']);
        $this->makeHidden('path');
        $this->append(['url', 'is_image', 'is_pdf']);
    }

    /**
     * Writes raw file content (e.g. an attachment sent through MCP) into the todo's directory like an
     * upload and returns the attributes describing it.
     *
     * @return array{path: string, original_name: string, caption: ?string, mime_type: ?string, size: int}
     */
    protected static function putContent(int $todoId, string $name, string $content, ?string $caption): array
    {
        $disk = Storage::disk(TodoComment::DISK);
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $path = TodoComment::directoryFor($todoId).'/'.Str::random(40).($extension !== '' ? '.'.$extension : '');

        $disk->put($path, $content);

        return [
            'path' => $path,
            'original_name' => $name,
            'caption' => $caption,
            'mime_type' => $disk->mimeType($path) ?: null,
            'size' => strlen($content),
        ];
    }

    /**
     * Name of the route that serves the file.
     */
    abstract protected function routeName(): string;

    public function getUrlAttribute(): string
    {
        return route($this->routeName(), $this);
    }

    public function getIsImageAttribute(): bool
    {
        return in_array($this->mime_type, self::IMAGE_TYPES, true);
    }

    public function getIsPdfAttribute(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    /**
     * Streams the file; images and PDFs open in the browser (and the gallery) unless a download is asked for.
     */
    public function toResponse(bool $download = false): StreamedResponse
    {
        $disk = Storage::disk(TodoComment::DISK);

        abort_unless($disk->exists($this->path), 404);

        $inline = ! $download && ($this->is_image || $this->is_pdf);

        $headers = [
            'Content-Type' => $this->mime_type ?? 'application/octet-stream',
            // Uploaded files must never run as HTML/JS in the app's origin.
            'X-Content-Type-Options' => 'nosniff',
        ];

        // A sandboxed document can't host the browser's PDF viewer; a sniffed PDF with nosniff can't run scripts anyway.
        if (! $this->is_pdf) {
            $headers['Content-Security-Policy'] = 'sandbox';
        }

        return $disk->response($this->path, $this->original_name, $headers, $inline ? 'inline' : 'attachment');
    }
}
