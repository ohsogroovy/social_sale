<?php

namespace App\Actions;

use App\Models\Tag;
use App\Clients\Shopify;
use App\Models\ReleasedTrigger;
use Illuminate\Support\Facades\DB;

class DeleteTags
{
    public function __construct(private Shopify $shopifyClient) {}

    /**
     * Delete tags by their IDs
     */
    public function execute(array $tagIds): void
    {
        DB::transaction(function () use ($tagIds) {
            $tags = Tag::whereIn('id', $tagIds)->get();

            foreach ($tags as $tag) {
                ReleasedTrigger::create(['name' => $tag->name]);

                if ($tag->product_id) {
                    $this->shopifyClient->removeTagFromProduct($tag->product->shopify_id, $tag->name);
                }
            }

            Tag::whereIn('id', $tagIds)->delete();
        });
    }
}
