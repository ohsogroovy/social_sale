<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PrivateMessage extends Model
{
    use HasFactory;

    protected $fillable = ['comment_id', 'page_id', 'recipient_id', 'message_id', 'message'];

    protected $casts = [
        'message' => 'array',
    ];

    /**
     * @return BelongsTo<Comment, PrivateMessage>
     */
    public function comment(): BelongsTo
    {
        return $this->belongsTo(Comment::class);
    }
}
