<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Support\Facades\Storage;

class ImportCsvController extends Controller
{
    public function importCsv(Post $post)
    {
        $post->load('comments');
        $csvHeaders = [
            'Post Date',
            'Post Type',
            'Message',
            'Commenter',
            'Comment',
            'Comment Date',
            'Post Link',
        ];

        $csvData = [];
        $csvData[] = [
            $post->created_at->format('Y-m-d'), $post->post_type, $post->message, '', '', '', '',
        ];

        foreach ($post->comments as $comment) {
            $csvData[] = [
                '', '', '', $comment->commenter, $comment->message, $comment->facebook_created_at->format('Y-m-d'), $comment->post_link ?? '',
            ];
        }
        $csvData = collect($csvData);
        $csvString = $csvData->prepend($csvHeaders)
            ->map(fn ($row) => sprintf('"%s"', implode('","', $row)))
            ->implode(\PHP_EOL);

        $fileName = 'post_'.$post->id.'_comments_report_'.now()->format('Y_m_d_H_i_s').'.csv';
        $filePath = 'reports/'.$fileName;

        Storage::put($filePath, $csvString);

        return response($csvString)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="'.$fileName.'"');

    }
}
