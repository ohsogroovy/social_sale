<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Tag extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'product_id', 'is_system_tag'];

    /**
     * @return BelongsTo<Product, Tag>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
