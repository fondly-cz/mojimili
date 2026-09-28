<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TodoComment;
use App\Models\User;
use App\Support\McpUpload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Receives files sent to a signed link from the create-upload-link MCP tool.
 */
class McpUploadController extends Controller
{
    public function __invoke(Request $request, User $user): JsonResponse
    {
        $max = TodoComment::MAX_FILE_KILOBYTES;

        $request->validate([
            'file' => "required_without:files|file|max:{$max}",
            'files' => 'required_without:file|array|max:20',
            'files.*' => "file|max:{$max}",
        ]);

        $files = array_values(array_filter([$request->file('file'), ...($request->file('files') ?? [])]));

        return response()->json([
            'uploads' => array_map(fn ($file) => McpUpload::store($user, $file), $files),
        ], 201);
    }
}
