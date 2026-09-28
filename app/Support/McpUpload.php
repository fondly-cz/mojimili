<?php

namespace App\Support;

use App\Models\TodoComment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Files uploaded through a signed link from create-upload-link, so an MCP client can send
 * a local file with a plain multipart request and then refer to it by its upload_id.
 * Uploads are private to the user who requested the link and are pruned after a day.
 */
class McpUpload
{
    public const DIRECTORY = 'mcp-uploads';

    public const LINK_MINUTES = 60;

    /**
     * @return array{upload_id: string, name: string, size: int}
     */
    public static function store(User $user, UploadedFile $file): array
    {
        $id = (string) Str::uuid();
        $name = basename($file->getClientOriginalName()) ?: 'soubor';

        Storage::disk(TodoComment::DISK)->putFileAs(self::directory($user, $id), $file, $name);

        return ['upload_id' => $id, 'name' => $name, 'size' => $file->getSize()];
    }

    /**
     * @return array{name: string, content: string}|null
     */
    public static function find(User $user, string $id): ?array
    {
        if (! Str::isUuid($id)) {
            return null;
        }

        $disk = Storage::disk(TodoComment::DISK);
        $path = $disk->files(self::directory($user, $id))[0] ?? null;

        return $path ? ['name' => basename($path), 'content' => $disk->get($path)] : null;
    }

    public static function prune(int $hours = 24): void
    {
        $disk = Storage::disk(TodoComment::DISK);
        $threshold = now()->subHours($hours)->getTimestamp();

        foreach ($disk->directories(self::DIRECTORY) as $userDirectory) {
            foreach ($disk->directories($userDirectory) as $upload) {
                $files = $disk->files($upload);

                if ($files === [] || $disk->lastModified($files[0]) < $threshold) {
                    $disk->deleteDirectory($upload);
                }
            }
        }
    }

    private static function directory(User $user, string $id): string
    {
        return self::DIRECTORY.'/'.$user->id.'/'.$id;
    }
}
