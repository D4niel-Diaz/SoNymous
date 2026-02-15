<?php

namespace App\Http\Controllers;

use App\Events\MessageCreated;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class MessageController extends Controller
{
    /**
 * GET /api/messages
 *
 * Return paginated non-expired messages (12 per page).
 * Supports optional ?category= filter and ?page= for pagination.
 */
public function index(Request $request): JsonResponse
{
    $query = Message::notExpired()
        ->orderByDesc('created_at');

    // Optional category filter
    if ($request->filled('category')) {
        $category = strip_tags(trim($request->query('category')));
        $query->where('category', $category);
    }

    $messages = $query->paginate(12);

    return response()->json([
        'status' => 'success',
        'data'   => $messages->items(),
        'meta'   => [
            'current_page' => $messages->currentPage(),
            'last_page'    => $messages->lastPage(),
            'total'        => $messages->total(),
        ],
    ]);
}

    /**
     * POST /api/messages
     *
     * Create a new anonymous message.
     * Validates & sanitizes input, hashes IP, sets expiry.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'content'  => 'required|string|max:200',
            'category' => 'nullable|string|max:50|in:advice,confession,fun',
        ]);

        if ($validator->fails()) {
            Log::warning('Message validation failed', [
                'errors' => $validator->errors()->toArray(),
                'ip'     => 'REDACTED',
            ]);

            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Sanitize content – strip HTML/JS tags to prevent XSS
        $sanitizedContent = strip_tags($validator->validated()['content']);

        // Hash the IP with app key as salt to prevent rainbow-table reversal
        $ipHash = hash_hmac('sha256', $request->ip(), config('app.key'));

        $message = Message::create([
            'content'    => $sanitizedContent,
            'ip_hash'    => $ipHash,
            'category'   => $validator->validated()['category'] ?? null,
            'expires_at' => now()->addHours(24),
        ]);

        // Refresh to include DB defaults (e.g. likes_count = 0)
        $message->refresh();

        // Broadcast for real-time updates (optional – fires only if configured)
        try {
            event(new MessageCreated($message));
        } catch (\Throwable) {
            // Broadcasting is optional; swallow errors silently
        }

        return response()->json([
            'status' => 'success',
            'data'   => $message,
        ], 201);
    }

    /**
     * POST /api/messages/{id}/like
     *
     * Increment likes_count anonymously.
     * Enforces one like per IP per message via cache.
     */
    public function like(Request $request, int $id): JsonResponse
    {
        $message = Message::notExpired()->find($id);

        if (! $message) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Message not found or has expired.',
            ], 404);
        }

        $ipHash   = hash_hmac('sha256', $request->ip(), config('app.key'));
        $cacheKey = "message_like:{$id}:{$ipHash}";

        // Atomic check-and-set: prevents race condition on concurrent requests
        if (! Cache::add($cacheKey, true, now()->addHours(24))) {
            return response()->json([
                'status'  => 'error',
                'message' => 'You have already liked this message.',
            ], 429);
        }

        $message->increment('likes_count');

        return response()->json([
            'status' => 'success',
            'data'   => [
                'likes_count' => $message->fresh()->likes_count,
            ],
        ]);
    }
}
