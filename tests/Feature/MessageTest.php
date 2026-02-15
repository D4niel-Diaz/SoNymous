<?php

use App\Models\Message;
use Illuminate\Support\Carbon;

/*
|--------------------------------------------------------------------------
| Feature Tests – Anonymous Student Message Wall API
|--------------------------------------------------------------------------
*/

// ─── POST /api/messages ──────────────────────────────────────────────

it('creates a message successfully', function () {
    $response = $this->postJson('/api/messages', [
        'content'  => 'Hello from a student!',
        'category' => 'advice',
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'status',
            'data' => ['id', 'content', 'category', 'created_at', 'likes_count'],
        ])
        ->assertJson([
            'status' => 'success',
            'data'   => [
                'content'  => 'Hello from a student!',
                'category' => 'advice',
            ],
        ])
        ->assertJsonMissing(['ip_hash']);

    $this->assertDatabaseHas('messages', [
        'content'  => 'Hello from a student!',
        'category' => 'advice',
    ]);
});

it('creates a message without a category', function () {
    $response = $this->postJson('/api/messages', [
        'content' => 'No category here',
    ]);

    $response->assertStatus(201)
        ->assertJson([
            'status' => 'success',
            'data'   => [
                'content'  => 'No category here',
                'category' => null,
            ],
        ]);
});

it('sets expires_at to 24 hours from now', function () {
    Carbon::setTestNow('2026-02-15 12:00:00');

    $this->postJson('/api/messages', ['content' => 'Temp message']);

    $message = Message::latest()->first();
    expect($message->expires_at->toDateTimeString())->toBe('2026-02-16 12:00:00');

    Carbon::setTestNow(); // reset
});

it('stores a hashed IP, not the raw IP', function () {
    $this->postJson('/api/messages', ['content' => 'Check IP']);

    $message = Message::latest()->first();
    expect($message->ip_hash)->toHaveLength(64) // SHA-256 hex
        ->and($message->ip_hash)->not->toBe('127.0.0.1');
});

// ─── Validation ──────────────────────────────────────────────────────

it('rejects empty content', function () {
    $response = $this->postJson('/api/messages', [
        'content' => '',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['content']);
});

it('rejects missing content', function () {
    $response = $this->postJson('/api/messages', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['content']);
});

it('rejects content longer than 200 characters', function () {
    $response = $this->postJson('/api/messages', [
        'content' => str_repeat('A', 201),
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['content']);
});

it('rejects invalid category', function () {
    $response = $this->postJson('/api/messages', [
        'content'  => 'Valid content',
        'category' => 'invalid_category',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['category']);
});

// ─── XSS Protection ─────────────────────────────────────────────────

it('strips HTML and script tags from content', function () {
    $response = $this->postJson('/api/messages', [
        'content' => '<script>alert("xss")</script>Hello',
    ]);

    $response->assertStatus(201);

    $message = Message::latest()->first();
    expect($message->content)->toBe('alert("xss")Hello')
        ->and($message->content)->not->toContain('<script>')
        ->and($message->content)->not->toContain('</script>');
});

it('strips nested HTML tags', function () {
    $response = $this->postJson('/api/messages', [
        'content' => '<div><b>Bold</b> <img src=x onerror=alert(1)>text</div>',
    ]);

    $response->assertStatus(201);

    $message = Message::latest()->first();
    expect($message->content)->not->toContain('<')
        ->and($message->content)->not->toContain('>');
});

// ─── GET /api/messages ───────────────────────────────────────────────

it('returns messages in the correct JSON format', function () {
    Message::factory()->count(3)->create();

    $response = $this->getJson('/api/messages');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'status',
            'data' => [
                '*' => ['id', 'content', 'category', 'created_at', 'likes_count'],
            ],
        ])
        ->assertJson(['status' => 'success']);

    // ip_hash must never appear in any item
    foreach ($response->json('data') as $item) {
        expect($item)->not->toHaveKey('ip_hash');
    }
});

it('returns at most 50 messages', function () {
    Message::factory()->count(60)->create();

    $response = $this->getJson('/api/messages');

    expect($response->json('data'))->toHaveCount(50);
});

it('filters messages by category', function () {
    Message::factory()->create(['category' => 'advice']);
    Message::factory()->create(['category' => 'fun']);
    Message::factory()->create(['category' => 'advice']);

    $response = $this->getJson('/api/messages?category=advice');

    $data = $response->json('data');
    expect($data)->toHaveCount(2)
        ->and(collect($data)->pluck('category')->unique()->values()->all())->toBe(['advice']);
});

it('excludes expired messages from GET', function () {
    // Active message
    Message::factory()->create([
        'content'    => 'I am active',
        'expires_at' => now()->addHours(12),
    ]);

    // Expired message
    Message::factory()->create([
        'content'    => 'I am expired',
        'expires_at' => now()->subHour(),
    ]);

    $response = $this->getJson('/api/messages');
    $data = $response->json('data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['content'])->toBe('I am active');
});

it('returns messages ordered by newest first', function () {
    $older = Message::factory()->create(['created_at' => now()->subMinutes(10)]);
    $newer = Message::factory()->create(['created_at' => now()]);

    $response = $this->getJson('/api/messages');
    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids[0])->toBe($newer->id);
});

// ─── POST /api/messages/{id}/like ────────────────────────────────────

it('increments likes_count', function () {
    $message = Message::factory()->create(['likes_count' => 0]);

    $response = $this->postJson("/api/messages/{$message->id}/like");

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'data'   => ['likes_count' => 1],
        ]);

    expect($message->fresh()->likes_count)->toBe(1);
});

it('returns 404 when liking a non-existent message', function () {
    $response = $this->postJson('/api/messages/9999/like');

    $response->assertStatus(404);
});

it('returns 404 when liking an expired message', function () {
    $message = Message::factory()->create([
        'expires_at' => now()->subHour(),
    ]);

    $response = $this->postJson("/api/messages/{$message->id}/like");

    $response->assertStatus(404);
});

// ─── Cleanup Command ────────────────────────────────────────────────

it('deletes expired messages via cleanup command', function () {
    Message::factory()->create(['expires_at' => now()->subHour()]);
    Message::factory()->create(['expires_at' => now()->subDay()]);
    Message::factory()->create(['expires_at' => now()->addHour()]); // still active

    $this->artisan('messages:cleanup')
        ->expectsOutput('Cleaned up 2 expired message(s).')
        ->assertExitCode(0);

    expect(Message::count())->toBe(1);
});

it('does nothing when there are no expired messages', function () {
    Message::factory()->create(['expires_at' => now()->addHour()]);

    $this->artisan('messages:cleanup')
        ->expectsOutput('Cleaned up 0 expired message(s).')
        ->assertExitCode(0);

    expect(Message::count())->toBe(1);
});

// ─── Security Tests (added by audit) ────────────────────────────────

it('prevents duplicate likes from the same IP', function () {
    $message = Message::factory()->create(['likes_count' => 0]);

    // First like succeeds
    $this->postJson("/api/messages/{$message->id}/like")
        ->assertStatus(200);

    // Second like from same IP is blocked
    $this->postJson("/api/messages/{$message->id}/like")
        ->assertStatus(429)
        ->assertJson(['status' => 'error']);

    expect($message->fresh()->likes_count)->toBe(1);
});

it('returns security headers on API responses', function () {
    $response = $this->getJson('/api/messages');

    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('X-Frame-Options', 'DENY');
    $response->assertHeader('Referrer-Policy', 'no-referrer');
});

it('uses HMAC-salted IP hash, not plain SHA256', function () {
    $this->postJson('/api/messages', ['content' => 'Hash check']);

    $message = Message::latest()->first();
    $expectedHash = hash_hmac('sha256', '127.0.0.1', config('app.key'));

    expect($message->ip_hash)->toBe($expectedHash);
});

it('sanitizes category filter on GET to prevent XSS', function () {
    Message::factory()->create(['category' => 'advice']);

    $response = $this->getJson('/api/messages?category=<script>alert(1)</script>');

    // Should return empty results, not error — the XSS is neutralized
    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(0);
});
