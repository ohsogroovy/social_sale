<?php

namespace App\Jobs;

use App\Actions\DeleteTags;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use App\Actions\GetSystemTagsInChunks;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class DeleteAllTagsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600; // 10 minutes timeout

    public int $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct() {}

    /**
     * Execute the job.
     */
    public function handle(GetSystemTagsInChunks $getSystemTagsInChunks, DeleteTags $deleteTags): void
    {
        try {
            \info('Starting delete all tags job');

            $chunkSize = 50;
            $chunks = $getSystemTagsInChunks->execute($chunkSize);
            $totalChunks = $chunks->count();
            $processedChunks = 0;

            \info('Processing tags in chunks', [
                'total_chunks' => $totalChunks,
                'chunk_size' => $chunkSize,
            ]);

            foreach ($chunks as $chunkIndex => $tagIdsChunk) {
                $deleteTags->execute($tagIdsChunk->toArray());
                $processedChunks++;

                \info('Processed chunk for deletion', [
                    'chunk' => $chunkIndex + 1,
                    'total_chunks' => $totalChunks,
                    'tag_count_in_chunk' => $tagIdsChunk->count(),
                ]);
            }

            \info('Completed processing all delete all tags chunks', [
                'total_chunks_processed' => $processedChunks,
            ]);
        } catch (\Exception $e) {
            \info('Failed to delete all tags', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Delete all tags job failed permanently', [
            'error' => $exception->getMessage(),
        ]);
    }
}
