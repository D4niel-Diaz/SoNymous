<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use HasFactory;
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'content',
        'ip_hash',
        'category',
        'expires_at',
        'is_deleted',
    ];

    /**
     * The attributes that should be hidden for serialization.
     * ip_hash is NEVER exposed to the frontend.
     */
    protected $hidden = [
        'ip_hash',
        'is_deleted',
        'updated_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'likes_count' => 'integer',
            'is_deleted'  => 'boolean',
            'created_at'  => 'datetime',
            'updated_at'  => 'datetime',
            'expires_at'  => 'datetime',
        ];
    }

    /**
     * Scope: only messages that are not soft-deleted.
     */
    public function scopeNotDeleted(Builder $query): Builder
    {
        return $query->where('is_deleted', false);
    }

    /**
     * Scope: only messages that have not expired AND are not deleted.
     * Used by all public-facing endpoints.
     */
    public function scopeNotExpired(Builder $query): Builder
    {
        return $query->notDeleted()->where(function (Builder $q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }
}
