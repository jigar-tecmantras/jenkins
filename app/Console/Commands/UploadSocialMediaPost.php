<?php

namespace App\Console\Commands;

use App\Http\Controllers\LinkedInController;
use App\Http\Response\CustomApiResponse;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Exception;

class UploadSocialMediaPost extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:upload-social-media-post';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Upload post on instagram or linkedin';
    protected $apiResponse;
    /**
     * @var linkedInController
     */
    private $linkedInController;

    public function __construct(LinkedInController $linkedInController, CustomApiResponse $customApiResponse)
    {
        parent::__construct();
        $this->linkedInController = $linkedInController;
        $this->apiResponse = $customApiResponse;
    }
    
    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            $user = $this->linkedInController->uploadpostOnSocialMedia();

            if (!empty($user)) {
                $success = [
                    $user
                ];
                $message = "Post uploaded successfully!";

                return $this->apiResponse->getResponseStructure(TRUE, $success, $message);
            }
            return true;
        } catch (Exception $e) {
            log::info($e);
            return $this->apiResponse->handleAndResponseException($e);
        }
        // try {
        //     $this->linkedInController->uploadpostOnSocialMedia();

        //     log::info("Done");
        // } catch (\Exception $e) {
        //     $message = 'Something has been going wrong with our system please refer to logs';
        //     Log::error($message . ' with message ' . $e->getMessage());
        //     $this->info($e->getMessage());
        // }
    }
}
