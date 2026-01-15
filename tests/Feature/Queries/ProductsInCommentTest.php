<?php

namespace Tests\Feature\Queries;

use App\Models\Tag;
use Tests\TestCase;
use App\Models\Comment;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProductsInCommentTest extends TestCase
{
    use RefreshDatabase;

    public function testGetProductsMentionedInComment(): void
    {
        $products = Product::factory()->has(Tag::factory()->count(3))->count(2)->create();
        $message = "

            I like's {$products[0]->tags->first()->name}  \n and {$products[1]->tags->first()->name}
            another thing
        ";
        $comment = Comment::factory()->create(\compact('message'));

        $productsInComment = (new \App\Queries\ProductsInComment)($comment)->get();

        $this->assertCount(2, $productsInComment);
    }

    public function testGetProductsNotMentionedInComment(): void
    {
        $products = Product::factory()->has(Tag::factory()->count(3))->count(2)->create();
        $comment = Comment::factory()->create(['message' => 'I like posting comments']);

        $productsInComment = (new \App\Queries\ProductsInComment)($comment)->get();

        $this->assertCount(0, $productsInComment);
    }

    public function testGetUniqueProductsMentionedInComment(): void
    {
        $referencedProduct = Product::factory()->has(Tag::factory(['name' => 'uniquetag']))->create();
        $comment = Comment::factory()->create(['message' => "I like {$referencedProduct->tags->first()->name} and {$referencedProduct->tags->first()->name}", 'is_from_page' => false]);
        $productsInComment = (new \App\Queries\ProductsInComment)($comment)->get();

        $this->assertCount(1, $productsInComment);
    }
}
