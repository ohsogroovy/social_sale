<?php

namespace App\Actions;

use App\Models\Tag;
use App\Models\Product;

class AddTags
{
    public function execute(Product $product, string $tags, ?Tag $systemTag = null): void
    {
        $tags = \explode(',', $tags);
        $tags = array_map('trim', $tags);
        $tags = \array_unique($tags);
        $tags = \array_filter($tags);

        $product->tags()->delete();

        $systemTagName = $systemTag ? $systemTag->name : null;
        $hasSystemTag = false;
        foreach ($tags as $tag) {
            if (\str_starts_with($tag, 'trigger-') == false) {
                continue;
            }
            $tagName = \trim(\substr($tag, 8));
            if (empty($tagName)) {
                continue;
            }

            if ($tagName === $systemTagName) {
                $hasSystemTag = true;
            }

            $tagAttrs = [
                'name' => $tagName,
                'product_id' => $product->id,
                'is_system_tag' => false,
            ];
            Tag::where($tagAttrs)->firstOr(fn () => Tag::create($tagAttrs));
        }
        if ($systemTag && $hasSystemTag) {
            Tag::create([
                'name' => $systemTag->name,
                'product_id' => $product->id,
                'is_system_tag' => true,
            ]);
        }
    }
}
