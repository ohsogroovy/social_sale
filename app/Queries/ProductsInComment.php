<?php

namespace App\Queries;

use App\Models\Comment;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;

class ProductsInComment
{
    /**
     * @param  Comment|string  $input
     * @return Builder<Product>
     */
    public function __invoke($input): Builder
    {
        if (is_string($input)) {
            $message = $input;
        } elseif ($input instanceof Comment) {
            $message = $input->getCleanMessageContent();
        } else {
            $message = '';
        }

        $messageLines = explode(PHP_EOL, $message);
        $messageText = implode(' ', $messageLines);
        $found = Product::query()
            ->whereHas('tags', function ($query) use ($messageText) {
                $words = explode(' ', $messageText);
                $words = array_map(fn ($word) => preg_replace('/[^A-Za-z0-9\-]/', '', $word), $words);
                $query->whereIn('name', $words);
            });

        return $found;
    }
}
