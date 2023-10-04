<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class DeletePublicFile extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:delete-public-file';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete files from public directory';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $directory = public_path('uploads/images');
        $files = File::files($directory);
        Log::info("test jenkins");
        // Loop through the files and delete them
        foreach ($files as $file) {
            File::delete($file);
        }
    }
}
