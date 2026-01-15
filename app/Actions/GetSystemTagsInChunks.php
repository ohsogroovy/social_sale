<?php

namespace App\Actions;

use App\Models\Tag;
use Illuminate\Support\Collection;

class GetSystemTagsInChunks
{
    /**
     * Get all system tag IDs in chunks
     *
     * @return Collection<int, Collection<int, int>>
     */
    public function execute(int $chunkSize = 50): Collection
    {
        /** @var Collection<int, int> $tagIds */
        $tagIds = Tag::where('is_system_tag', true)->pluck('id');

        return $tagIds->chunk($chunkSize);
    }
}
