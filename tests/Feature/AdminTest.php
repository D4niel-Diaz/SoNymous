<?php

use App\Models\Admin;
use App\Models\Message;

/*
|--------------------------------------------------------------------------
| Feature Tests – Admin Moderation API
|--------------------------------------------------------------------------
*/

// ─── POST /api/admin/login ──────────────────────────────────────────

it('logs in an admin with valid credentials', function () {
    $admin = Admin::factory()->create(['password' => 'secret123']);

    $response = $this->postJson('/api/admin/login', [
        'email'    => $admin->email,
        'password' => 'secret123',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'status',
            'data' => ['token', 'admin' => ['id', 'name', 'email']],
        ])
        ->assertJson([
            'status' => 'success',
            'data'   => [
                'admin' => [
                    'id'    => $admin->id,
                    'email' => $admin->email,
                ],
            ],
        ]);

    // Token must be a non-empty string
    expect($response->json('data.token'))->toBeString()->not->toBeEmpty();
});

it('rejects admin login with wrong password', function () {
    $admin = Admin::factory()->create(['password' => 'secret123']);

    $response = $this->postJson('/api/admin/login', [
        'email'    => $admin->email,
        'password' => 'wrongpassword',
    ]);

    $response->assertStatus(401)
        ->assertJson([
            'status'  => 'error',
            'message' => 'Invalid credentials.',
        ]);
});

it('rejects admin login with non-existent email', function () {
    $response = $this->postJson('/api/admin/login', [
        'email'    => 'nobody@example.com',
        'password' => 'whatever',
    ]);

    $response->assertStatus(401)
        ->assertJson(['status' => 'error']);
});

it('rejects admin login with missing fields', function () {
    $response = $this->postJson('/api/admin/login', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email', 'password']);
});

// ─── DELETE /api/admin/messages/{id} ────────────────────────────────

it('allows admin to soft-delete a message', function () {
    $admin   = Admin::factory()->create();
    $message = Message::factory()->create(['is_deleted' => false]);

    $response = $this->actingAs($admin, 'admin')
        ->deleteJson("/api/admin/messages/{$message->id}");

    $response->assertStatus(200)
        ->assertJson([
            'status'  => 'success',
            'message' => 'Message has been deleted.',
            'data'    => [
                'id'         => $message->id,
                'is_deleted' => true,
            ],
        ]);

    expect($message->fresh()->is_deleted)->toBeTrue();
});

it('returns 404 when admin deletes non-existent message', function () {
    $admin = Admin::factory()->create();

    $response = $this->actingAs($admin, 'admin')
        ->deleteJson('/api/admin/messages/9999');

    $response->assertStatus(404);
});

it('returns 409 when admin deletes an already-deleted message', function () {
    $admin   = Admin::factory()->create();
    $message = Message::factory()->create(['is_deleted' => true]);

    $response = $this->actingAs($admin, 'admin')
        ->deleteJson("/api/admin/messages/{$message->id}");

    $response->assertStatus(409)
        ->assertJson([
            'status'  => 'error',
            'message' => 'Message is already deleted.',
        ]);
});

it('rejects delete without admin token', function () {
    $message = Message::factory()->create();

    $response = $this->deleteJson("/api/admin/messages/{$message->id}");

    $response->assertStatus(401);
});

// ─── GET /api/admin/messages ────────────────────────────────────────

it('returns all messages including deleted for admin', function () {
    $admin = Admin::factory()->create();

    Message::factory()->create(['is_deleted' => false]);
    Message::factory()->create(['is_deleted' => true]);

    $response = $this->actingAs($admin, 'admin')
        ->getJson('/api/admin/messages');

    $response->assertStatus(200)
        ->assertJson(['status' => 'success']);

    expect($response->json('data'))->toHaveCount(2);
});

it('filters admin messages by is_deleted', function () {
    $admin = Admin::factory()->create();

    Message::factory()->count(3)->create(['is_deleted' => false]);
    Message::factory()->count(2)->create(['is_deleted' => true]);

    $response = $this->actingAs($admin, 'admin')
        ->getJson('/api/admin/messages?is_deleted=true');

    expect($response->json('data'))->toHaveCount(2);
});

it('filters admin messages by category', function () {
    $admin = Admin::factory()->create();

    Message::factory()->create(['category' => 'advice']);
    Message::factory()->create(['category' => 'fun']);

    $response = $this->actingAs($admin, 'admin')
        ->getJson('/api/admin/messages?category=advice');

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.category'))->toBe('advice');
});

it('rejects admin messages list without token', function () {
    $response = $this->getJson('/api/admin/messages');

    $response->assertStatus(401);
});

// ─── Public API excludes deleted messages ───────────────────────────

it('public GET /api/messages excludes soft-deleted messages', function () {
    Message::factory()->create([
        'content'    => 'visible message',
        'is_deleted' => false,
    ]);
    Message::factory()->create([
        'content'    => 'deleted message',
        'is_deleted' => true,
    ]);

    $response = $this->getJson('/api/messages');

    $data = $response->json('data');
    expect($data)->toHaveCount(1)
        ->and($data[0]['content'])->toBe('visible message');
});

it('public POST like rejects soft-deleted messages', function () {
    $message = Message::factory()->create(['is_deleted' => true]);

    $response = $this->postJson("/api/messages/{$message->id}/like");

    $response->assertStatus(404);
});
