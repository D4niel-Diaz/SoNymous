<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AdminController extends Controller
{
    /**
     * POST /api/admin/login
     *
     * Authenticate an admin and return a Sanctum token.
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $admin = Admin::where('email', $validator->validated()['email'])->first();

        if (! $admin || ! Hash::check($validator->validated()['password'], $admin->password)) {
            Log::warning('Admin login failed', [
                'email' => $validator->validated()['email'],
                'ip'    => 'REDACTED',
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Invalid credentials.',
            ], 401);
        }

        // Revoke previous tokens for this admin (single-session)
        $admin->tokens()->delete();

        $token = $admin->createToken('admin-token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'data'   => [
                'token' => $token,
                'admin' => [
                    'id'    => $admin->id,
                    'name'  => $admin->name,
                    'email' => $admin->email,
                ],
            ],
        ]);
    }

    /**
     * GET /api/admin/messages
     *
     * Return all messages (including deleted) for admin moderation.
     * Supports optional ?is_deleted= and ?category= filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Message::orderByDesc('created_at');

        // Optional category filter
        if ($request->filled('category')) {
            $category = strip_tags(trim($request->query('category')));
            $query->where('category', $category);
        }

        // Optional is_deleted filter
        if ($request->filled('is_deleted')) {
            $isDeleted = filter_var($request->query('is_deleted'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if (! is_null($isDeleted)) {
                $query->where('is_deleted', $isDeleted);
            }
        }

        $messages = $query->paginate(50);

        // Make is_deleted visible for admin view (hidden by default for public API)
        $messages->getCollection()->each->makeVisible('is_deleted');

        return response()->json([
            'status' => 'success',
            'data'   => $messages->items(),
            'meta'   => [
                'current_page' => $messages->currentPage(),
                'last_page'    => $messages->lastPage(),
                'per_page'     => $messages->perPage(),
                'total'        => $messages->total(),
            ],
        ]);
    }

    /**
     * DELETE /api/admin/messages/{id}
     *
     * Soft-delete a message by setting is_deleted = true.
     * Logs the admin action for audit trail.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $message = Message::find($id);

        if (! $message) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Message not found.',
            ], 404);
        }

        if ($message->is_deleted) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Message is already deleted.',
            ], 409);
        }

        $message->update(['is_deleted' => true]);

        // Audit log
        Log::info('Admin moderation: message deleted', [
            'admin_id'   => $request->user()->id,
            'admin_email' => $request->user()->email,
            'message_id' => $id,
            'action'     => 'soft_delete',
            'timestamp'  => now()->toIso8601String(),
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Message has been deleted.',
            'data'    => [
                'id'         => $message->id,
                'is_deleted' => true,
            ],
        ]);
    }
}
