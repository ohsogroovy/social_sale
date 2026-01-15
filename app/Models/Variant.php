<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Variant extends Model
{
    use HasFactory;

    protected $fillable = ['product_id', 'shopify_id', 'name', 'sku', 'quantity'];

    /**
     * @return BelongsTo<Product, Variant>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
