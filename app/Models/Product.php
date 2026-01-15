<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Product extends Model
{
    use HasFactory;

    protected $fillable = ['shopify_id', 'name', 'handle', 'image_url', 'short_description'];

    /**
     * @return HasMany<Tag, Product>
     */
    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class);
    }

    /**
     * @return HasMany<Variant, Product>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(Variant::class);
    }

    public function storeUrl(): string
    {
        return 'https://'.\config('services.shopify_store.name').'/products/'.$this->handle;
    }
}
