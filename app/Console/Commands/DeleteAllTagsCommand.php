<?php

namespace App\Console\Commands;

use App\Models\Tag;
use App\Jobs\DeleteAllTagsJob;
use Illuminate\Console\Command;

class DeleteAllTagsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tags:delete-all {--force : Force deletion without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete all system generated tags via background job';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $systemTagsCount = Tag::where('is_system_tag', true)->count();

        if ($systemTagsCount === 0) {
            $this->info('No system tags found to delete.');

            return 0;
        }

        $this->info("Found {$systemTagsCount} system tags to delete.");

        if (! $this->option('force')) {
            if (! $this->confirm('Are you sure you want to delete all system tags? This action cannot be undone.')) {
                $this->info('Operation cancelled.');

                return 0;
            }
        }

        DeleteAllTagsJob::dispatch();

        $this->info("Delete all tags job has been dispatched! {$systemTagsCount} tags will be processed in background.");
        $this->info('You can monitor the job progress in the logs.');

        return 0;
    }
}
