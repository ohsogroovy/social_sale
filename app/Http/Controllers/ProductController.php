<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use Inertia\Inertia;
use App\Models\Product;
use App\Actions\DeleteTags;
use Illuminate\Http\Request;
use App\Actions\SearchProduct;
use App\Jobs\DeleteAllTagsJob;
use App\Actions\AssignTriggerNumber;
use Illuminate\Support\Facades\Auth;

class ProductController extends Controller
{
    public function __construct(
        private SearchProduct $searchProduct,
        private AssignTriggerNumber $assignTriggerNumber,
        private DeleteTags $deleteTags,
    ) {}

    public function searchProducts(Request $request)
    {
        $request->validate([
            'sku' => 'required|string',
        ]);

        $sku = $request->input('sku');
        $result = $this->searchProduct->execute($sku);

        if ($result['error'] === true) {
            return response()->json(['message' => $result['message']], $result['status']);
        }

        $shortestTag = $result['data']['shortestTag'];
        $productName = $result['data']['productName'];
        $tracksInventory = $result['data']['tracksInventory'];
        $quantity = $result['data']['quantity'];
        $productId = $result['data']['productShopifyId'];

        $autoTrigger = null;
        if (Auth::user()->auto_trigger) {
            $product = Product::where('shopify_id', $productId)->first();
            if ($product) {
                $autoTrigger = $this->assignTriggerNumber->execute($product, $sku);
            }
        }
        $message = "\"$productName\" \"$shortestTag\"";
        if ($tracksInventory === true) {
            $message .= '. Only ' . ($quantity > 10 ? '10+ left' : "$quantity left") . '!';
        }
        $message .= ' Reply to this comment to purchase.';

        $response = [
            'message' => $message,
            'data' => ['shortestTag' => $shortestTag],
        ];

        if ($autoTrigger !== null) {
            $response['autoTrigger'] = [
                'sku' => $sku,
                'productName' => $productName,
                'triggerTag' => $autoTrigger,
                'quantity' => $quantity,
            ];
        }

        return response()->json($response, $result['status']);
    }

    public function generatedTags()
    {
        $tags = Tag::where('is_system_tag', true)
            ->with('product')->orderBy('created_at', 'desc')
            ->paginate(15);

        return Inertia::render('Tags/Show', [
            'tags' => $tags,
        ]);
    }

    public function deleteTags(Request $request)
    {
        $request->validate([
            'tag_ids' => ['required', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
        ]);

        // Use the action directly for synchronous processing
        $this->deleteTags->execute($request->tag_ids);

        return response()->json([
            'success' => true,
            'message' => 'Tags deleted and moved to released_triggers successfully.',
        ]);
    }

    public function deleteAllTags()
    {
        // Check if there are any system tags to delete
        $systemTagsCount = Tag::where('is_system_tag', true)->count();

        if ($systemTagsCount === 0) {
            return response()->json([
                'success' => false,
                'message' => 'No system tags found to delete.',
            ], 404);
        }

        // Dispatch job for background processing of all tags
        DeleteAllTagsJob::dispatch();

        return response()->json([
            'success' => true,
            'message' => "{$systemTagsCount} tags will be processed in the background.",
            'data' => null,
        ]);
    }
}
