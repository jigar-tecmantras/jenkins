<?php

namespace App\Thread;

use App\Thread\Thread as ThreadThread;
use Thread;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
class LinkedInThread extends ThreadThread
{
    private $request;
    private $imagePath;
    protected $app_id;
    protected $app_secret;
    protected $callback;
    protected $scopes;
    protected $ssl;

    public function __construct()
    {
        // $this->request = $request;
        // $this->imagePath = $imagePath;
        // $this->ssl = $ssl;
    }
   
    public function run()
    {
        $this->uploadPostOnLinkedIn();
    }

    private function uploadPostOnLinkedIn()
    {
        sleep(2);
        $datas = "134";
        return $datas; 
        // Posting
        $post_url =
            "https://api.linkedin.com/rest/assets?action=registerUpload?oauth2_access_token=" .
            $request["linkedInToken"];

        // Adding Medias
        $media = [];
        // if ($request["type"] == "image") {
        //     foreach ($imagePath as $key => $image) {
        //         // Preparing Request
        //         $prepareUrl =
        //             "https://api.linkedin.com/v2/assets?action=registerUpload&oauth2_access_token=" .
        //             $request["linkedInToken"];
        //         $prepareRequest = [
        //             "registerUploadRequest" => [
        //                 "recipes" => [
        //                     "urn:li:digitalmediaRecipe:feedshare-image",
        //                 ],
        //                 "owner" => "urn:li:person:" . $request["person_id"],
        //                 "serviceRelationships" => [
        //                     [
        //                         "relationshipType" => "OWNER",
        //                         "identifier" =>
        //                         "urn:li:userGeneratedContent",
        //                     ],
        //                 ],
        //             ],
        //         ];

        //         try {
        //             $prepareReponse = $this->curl(
        //                 $prepareUrl,
        //                 json_encode($prepareRequest),
        //                 "application/json"
        //             );

        //             if (isset(json_decode($prepareReponse)->value)) {
        //                 $uploadURL = json_decode($prepareReponse)->value
        //                     ->uploadMechanism
        //                     ->{"com.linkedin.digitalmedia.uploading.MediaUploadHttpRequest"}
        //                     ->uploadUrl;
        //                 $asset_id = json_decode($prepareReponse)->value
        //                     ->asset;
        //                 $images[$key]["asset_id"] = $asset_id;
        //                 $destinationPath = public_path("uploads/images");
        //                 // if (!File::isDirectory($destinationPath)) {
        //                 //     File::makeDirectory(
        //                 //         $destinationPath,
        //                 //         0777,
        //                 //         true,
        //                 //         true
        //                 //     );
        //                 // }

        //                 // $imagePath =
        //                 //     date("YmdHisv") .
        //                 //     rand(1, 1000000) .
        //                 //     "." .
        //                 //     $image->getClientOriginalExtension();
        //                 // if ($image->move($destinationPath, $imagePath)) {
        //                 //     $imageArr["name"] = $imagePath;
        //                 //     $imageArr["path"] = "uploads/images";
        //                 //     $imageArr[
        //                 //         "extension"
        //                 //     ] = $image->getClientOriginalExtension();
        //                 // }
        //                 $imageArr["path"] = "uploads/images";
        //                 $imagePathUrl = $imageArr["path"] . "/" . $image;
        //                 $imageName[] = $imagePathUrl;
        //                 $imageData = file_get_contents($imagePathUrl); // Read the image file as binary data
        //                 $registerUpload = Http::withHeaders([
        //                     "Authorization" =>
        //                     "Bearer " . $request["linkedInToken"],
        //                 ])
        //                     ->attach("file", $imageData, $imagePathUrl)
        //                     ->put($uploadURL)
        //                     ->json();
        //             }
        //             // else {
        //             //     throw new Exception("Unauthorized Access!");
        //             // }
        //         } catch (Exception $e) {
        //             // Handle the exception here
        //             // Log the error message or return an error response to the user
        //             return response()->json(
        //                 ["message" => $e->getMessage()],
        //                 401
        //             );
        //         }
        //     }

        //     $contentEntities = [];

        //     foreach ($images as $image_id) {
        //         $contentEntities[] = [
        //             "entity" => $image_id["asset_id"],
        //         ];
        //     }
        //     try {
        //         $requestparam = [
        //             "owner" => "urn:li:person:" . $request["person_id"],
        //             "text" => [
        //                 "text" => !empty($request["text"])
        //                     ? $request["text"]
        //                     : "",
        //             ],
        //             "subject" => "Test Share Subject",
        //             "distribution" => [
        //                 "linkedInDistributionTarget" => [
        //                     "visibleToGuest" => true,
        //                 ],
        //             ],
        //             "content" => [
        //                 "contentEntities" => $contentEntities,
        //                 "title" => "Test Share with Content title",
        //                 "shareMediaCategory" => "IMAGE",
        //             ],
        //         ];
        //         $registerUpload = Http::withHeaders([
        //             "Authorization" =>
        //             "Bearer " . $request["linkedInToken"],
        //         ])
        //             ->post(
        //                 "https://api.linkedin.com/v2/shares",
        //                 $requestparam
        //             )
        //             ->json();
        //     } catch (Exception $e) {
        //         // Handle the exception here
        //         // Log the error message or return an error response to the user
        //         return response()->json(
        //             ["message" => $e->getMessage()],
        //             401
        //         );
        //     }
        // }
        // if ($request["type"] == "text") {
        //     $requestparam = [
        //         "author" => "urn:li:person:" . $request["person_id"],
        //         "lifecycleState" => "PUBLISHED",
        //         "specificContent" => [
        //             "com.linkedin.ugc.ShareContent" => [
        //                 "shareCommentary" => [
        //                     "text" => $request["text"],
        //                 ],
        //                 "shareMediaCategory" => "NONE",
        //             ],
        //         ],
        //         "visibility" => [
        //             "com.linkedin.ugc.MemberNetworkVisibility" => "PUBLIC",
        //         ],
        //     ];
        //     $registerUpload = Http::withHeaders([
        //         "Authorization" => "Bearer " . $request["linkedInToken"],
        //     ])
        //         ->post(
        //             "https://api.linkedin.com/v2/ugcPosts",
        //             $requestparam
        //         )
        //         ->json();
        // }

        // if ($request["type"] == "video") {
        //     // Preparing Request
        //     $prepareUrl =
        //         "https://api.linkedin.com/v2/assets?action=registerUpload&oauth2_access_token=" .
        //         $request["linkedInToken"];
        //     $prepareRequest = [
        //         "registerUploadRequest" => [
        //             "recipes" => [
        //                 "urn:li:digitalmediaRecipe:feedshare-video",
        //             ],
        //             "owner" => "urn:li:person:" . $request["person_id"],
        //             "serviceRelationships" => [
        //                 [
        //                     "relationshipType" => "OWNER",
        //                     "identifier" => "urn:li:userGeneratedContent",
        //                 ],
        //             ],
        //         ],
        //     ];
        //     try {
        //         $prepareReponse = $this->curl(
        //             $prepareUrl,
        //             json_encode($prepareRequest),
        //             "application/json"
        //         );
        //         if (isset(json_decode($prepareReponse)->value)) {
        //             $uploadURL = json_decode($prepareReponse)->value
        //                 ->uploadMechanism
        //                 ->{"com.linkedin.digitalmedia.uploading.MediaUploadHttpRequest"}
        //                 ->uploadUrl;
        //             $asset_id = json_decode($prepareReponse)->value->asset;
        //             Log::info($asset_id);
        //             $destinationPath = public_path("uploads/images");
        //             // if (!File::isDirectory($destinationPath)) {
        //             //     File::makeDirectory(
        //             //         $destinationPath,
        //             //         0777,
        //             //         true,
        //             //         true
        //             //     );
        //             // }
        //             // $file = $request->file("files")[0]; // get the first uploaded file
        //             // $imagePath =
        //             //     date("YmdHisv") .
        //             //     rand(1, 1000000) .
        //             //     "." .
        //             //     $file->getClientOriginalExtension();
        //             // if ($file->move($destinationPath, $imagePath)) {
        //             //     $imageArr["name"] = $imagePath;
        //             //     $imageArr["path"] = "uploads/images";
        //             //     $imageArr[
        //             //         "extension"
        //             //     ] = $file->getClientOriginalExtension();
        //             // }
        //             $imageArr["path"] = "uploads/images";
        //             $imagePathUrl =
        //                 $imageArr["path"] . "/" . $imagePath[0];;
        //             Log::info($imagePathUrl);
        //             $content = file_get_contents($imagePathUrl);
        //             $registerUpload = Http::timeout(1200)
        //                 ->withHeaders([
        //                     "Authorization" =>
        //                     "Bearer " . $request["linkedInToken"],
        //                 ])
        //                 ->attach("file", $content, $imagePathUrl)
        //                 ->put($uploadURL)
        //                 ->json();
        //             Log::info($registerUpload);
        //         }
        //         // else {
        //         //     throw new Exception("Unauthorized Access!");
        //         // }
        //     } catch (Exception $e) {
        //         // Handle the exception here
        //         // Log the error message or return an error response to the user
        //         return response()->json(
        //             ["message" => $e->getMessage()],
        //             401
        //         );
        //     }
        //     $contentEntities = [];
        //     $requestparam = [
        //         "author" => "urn:li:person:" . $request["person_id"],
        //         "lifecycleState" => "PUBLISHED",
        //         "specificContent" => [
        //             "com.linkedin.ugc.ShareContent" => [
        //                 "media" => [
        //                     [
        //                         "media" => $asset_id,
        //                         "status" => "READY",
        //                     ],
        //                 ],
        //                 "shareCommentary" => [
        //                     "text" => !empty($request["text"])
        //                         ? $request["text"]
        //                         : "",
        //                 ],
        //                 "shareMediaCategory" => "VIDEO",
        //             ],
        //         ],
        //         "visibility" => [
        //             "com.linkedin.ugc.MemberNetworkVisibility" => "PUBLIC",
        //         ],
        //     ];

        //     $registerUpload = Http::withHeaders([
        //         "Authorization" => "Bearer " . $request["linkedInToken"],
        //     ])
        //         ->post(
        //             "https://api.linkedin.com/v2/ugcPosts",
        //             $requestparam
        //         )
        //         ->json();
        // }
        $registerUpload = "";
        return $registerUpload;
    }

    public function curl($url, $parameters, $content_type, $post = true)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->ssl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 7200);
        if ($post) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $parameters);
        }
        curl_setopt($ch, CURLOPT_POST, $post);
        $headers = [];
        $headers[] = "Content-Type: {$content_type}";
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $result = curl_exec($ch);
        return $result;
    }
}