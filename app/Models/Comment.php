<?php

namespace App\Models;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Comment extends Model
{
    use HasFactory;

    protected $fillable = ['facebook_user_id', 'commenter', 'facebook_id', 'post_id', 'parent_id', 'post_link', 'post_type', 'message', 'facebook_created_at', 'is_from_page'];

    protected $casts = [
        'facebook_created_at' => 'datetime',
        'is_from_page' => 'boolean',
    ];

    /**
     * @return HasOne<PrivateMessage>
     */
    public function privateMessage(): HasOne
    {
        return $this->hasOne(PrivateMessage::class);
    }

    /**
     * @return BelongsTo<Post, Comment>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'post_id', 'facebook_id');
    }

    public function getCleanMessageContent(): string
    {
        $message = $this->message;
        $message = \str_replace("'", '', $message);
        $message = Str::squish($message);

        return $message;
    }

    public function getPageId(): int
    {
        return (int) explode('_', $this->post_id)[0];
    }

    public function parent(): self
    {
        return self::where('facebook_id', $this->parent_id)->first();
    }

    /**
     * Check if the comment is a reply to a page's comment.
     */
    public function isReplyToPage(): bool
    {
        return self::where('facebook_id', $this->parent_id)->where('is_from_page', true)->exists();
    }

    public function messageContains(string $str): bool
    {
        return Str::contains(Str::lower($this->message), $str);
    }
}
