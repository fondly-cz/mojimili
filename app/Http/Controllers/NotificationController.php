<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'unread' => $user->unreadNotifications()->count(),
            'items' => $user->notifications()->latest()->limit(20)->get()->map(fn ($notification) => [
                'id' => $notification->id,
                'message' => $notification->data['message'] ?? '',
                'todo_name' => $notification->data['todo_name'] ?? '',
                'url' => $notification->data['url'] ?? '/',
                'read' => $notification->read_at !== null,
                'created_at' => $notification->created_at->toIso8601String(),
            ]),
        ]);
    }

    public function read(Request $request, string $id): JsonResponse
    {
        $request->user()->notifications()->whereKey($id)->first()?->markAsRead();

        return response()->json(['ok' => true]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }
}
