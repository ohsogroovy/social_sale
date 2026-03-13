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

        $words = preg_split('/\s+/', $messageText);
        $words = array_map(fn ($word) => preg_replace('/[^A-Za-z0-9\-]/', '', $word), $words);
        $words = array_filter($words);

        // Debug log: log the extracted words from the comment
        \Log::info('ProductsInComment: Extracted words from comment', [
            'original_message' => $message,
            'words' => $words,
        ]);

        $found = Product::query()
            ->whereHas('tags', function ($query) use ($words) {
                foreach ($words as $word) {
                    $query->orWhereRaw('LOWER(name) = ?', [strtolower($word)]);
                }
            });

        return $found;
    }
}
