<?php

namespace App\Repositories;

use App\Exceptions\LinkedInFailException;
use App\Models\SocialMediaPayload;
use Exception;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Abraham\TwitterOAuth\TwitterOAuth;
use Abraham\TwitterOAuth\TwitterOAuthException;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Subscriber\Oauth\Oauth1;

/**
 * Class SocialMediaRepository.
 */
class SocialMediaRepository
{
    protected $app_id;
    protected $app_secret;
    protected $callback;
    protected $scopes;
    protected $ssl;

    public function __construct(bool $ssl = true)
    {
        $this->ssl = $ssl;
    }

    public function imageShare($request)
    {
        $promises = [];
        $imagePath = [];

        if ($request->hasFile('files') && count($request->file("files")) > 0) {
            foreach ($request->file("files") as $image) {
                $imagePath[] = $this->moveFileToDestination($image);
            }
        }

        if (isset($request["socicalType"]) && is_array($request["socicalType"]) && in_array("LinkedIn", $request["socicalType"])) {
            $results = $this->uploadPostOnLinkedIn($request, $imagePath);
        }

        if (isset($request["socicalType"]) && is_array($request["socicalType"]) && in_array("Instagram", $request["socicalType"])) {
            $results = $this->uploadPostOnInstagram($request, $imagePath);
        }
        if (isset($request["socicalType"]) && is_array($request["socicalType"]) && in_array("Facebook", $request["socicalType"])) {
            $results = $this->uploadPostOnFacebook($request, $imagePath);
        }

        if (isset($request["socicalType"]) && is_array($request["socicalType"]) && in_array("Twitter", $request["socicalType"])) {
            $results = $this->uploadPostOnTwitter($request, $imagePath);
        }

        return $results;
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

    public function uploadSchedulePost($request)
    {
        $payload = $request->all();
        if (count($request->files) > 0) {
            foreach ($request['files'] as $file) {
                $destinationPath = public_path("uploads/images");
                if (!File::isDirectory($destinationPath)) {
                    File::makeDirectory($destinationPath, 0777, true, true);
                }

                $imagePath =
                    date("YmdHisv") .
                    rand(1, 1000000) .
                    "." .
                    $file->getClientOriginalExtension();
                if ($file->move($destinationPath, $imagePath)) {
                    $imageNames[] = $imagePath;
                }
            }
            $imageNamesString = implode(',', $imageNames);
            $payload['files'] = $imageNamesString;
        }
        $cretaePayload = [
            'payload' => json_encode($payload),
            'upload_time' => $request['upload_time']
        ];

        return SocialMediaPayload::create($cretaePayload);
    }


    public function uploadPostOnlinkedIn($request, $imagePath)
    {
        // Posting
        $post_url =
            "https://api.linkedin.com/rest/assets?action=registerUpload?oauth2_access_token=" .
            $request["linkedInToken"];

        // Adding Medias
        $media = [];
        if ($request["type"] == "image") {
            foreach ($imagePath as $key => $image) {
                // Preparing Request
                $prepareUrl =
                    "https://api.linkedin.com/v2/assets?action=registerUpload&oauth2_access_token=" .
                    $request["linkedInToken"];
                $prepareRequest = [
                    "registerUploadRequest" => [
                        "recipes" => [
                            "urn:li:digitalmediaRecipe:feedshare-image",
                        ],
                        "owner" => "urn:li:person:" . $request["person_id"],
                        "serviceRelationships" => [
                            [
                                "relationshipType" => "OWNER",
                                "identifier" =>
                                "urn:li:userGeneratedContent",
                            ],
                        ],
                    ],
                ];

                try {
                    $prepareReponse = $this->curl(
                        $prepareUrl,
                        json_encode($prepareRequest),
                        "application/json"
                    );

                    if (isset(json_decode($prepareReponse)->value)) {
                        $uploadURL = json_decode($prepareReponse)->value
                            ->uploadMechanism
                            ->{"com.linkedin.digitalmedia.uploading.MediaUploadHttpRequest"}
                            ->uploadUrl;
                        $asset_id = json_decode($prepareReponse)->value
                            ->asset;
                        $images[$key]["asset_id"] = $asset_id;
                        $destinationPath = public_path("uploads/images");
                        $imageArr["path"] = "uploads/images";
                        $imagePathUrl = $imageArr["path"] . "/" . $image;
                        $imageName[] = $imagePathUrl;
                        $imageData = file_get_contents($imagePathUrl); // Read the image file as binary data
                        $registerUpload = Http::withHeaders([
                            "Authorization" =>
                            "Bearer " . $request["linkedInToken"],
                        ])
                            ->attach("file", $imageData, $imagePathUrl)
                            ->put($uploadURL)
                            ->json();
                    }
                    // else {
                    //     throw new Exception("Unauthorized Access!");
                    // }
                } catch (Exception $e) {
                    return response()->json(
                        ["message" => $e->getMessage()],
                        JsonResponse::HTTP_UNAUTHORIZED // Set the desired status code here
                    )->getStatusCode();
                }
            }

            $contentEntities = [];

            foreach ($images as $image_id) {
                $contentEntities[] = [
                    "entity" => $image_id["asset_id"],
                ];
            }
            try {
                $requestparam = [
                    "owner" => "urn:li:person:" . $request["person_id"],
                    "text" => [
                        "text" => !empty($request["text"])
                            ? $request["text"]
                            : "",
                    ],
                    "subject" => "Test Share Subject",
                    "distribution" => [
                        "linkedInDistributionTarget" => [
                            "visibleToGuest" => true,
                        ],
                    ],
                    "content" => [
                        "contentEntities" => $contentEntities,
                        "title" => "Test Share with Content title",
                        "shareMediaCategory" => "IMAGE",
                    ],
                ];
                $registerUpload = Http::withHeaders([
                    "Authorization" =>
                    "Bearer " . $request["linkedInToken"],
                ])
                    ->post(
                        "https://api.linkedin.com/v2/shares",
                        $requestparam
                    );
            } catch (Exception $e) {
                return response()->json(
                    ["message" => $e->getMessage()],
                    JsonResponse::HTTP_UNAUTHORIZED // Set the desired status code here
                )->getStatusCode();
            }
        }
        if ($request["type"] == "text") {
            $requestparam = [
                "author" => "urn:li:person:" . $request["person_id"],
                "lifecycleState" => "PUBLISHED",
                "specificContent" => [
                    "com.linkedin.ugc.ShareContent" => [
                        "shareCommentary" => [
                            "text" => $request["text"],
                        ],
                        "shareMediaCategory" => "NONE",
                    ],
                ],
                "visibility" => [
                    "com.linkedin.ugc.MemberNetworkVisibility" => "PUBLIC",
                ],
            ];
            $registerUpload = Http::withHeaders([
                "Authorization" => "Bearer " . $request["linkedInToken"],
            ])
                ->post(
                    "https://api.linkedin.com/v2/ugcPosts",
                    $requestparam
                );
        }

        if ($request["type"] == "video") {
            // Preparing Request
            $prepareUrl =
                "https://api.linkedin.com/v2/assets?action=registerUpload&oauth2_access_token=" .
                $request["linkedInToken"];
            $prepareRequest = [
                "registerUploadRequest" => [
                    "recipes" => [
                        "urn:li:digitalmediaRecipe:feedshare-video",
                    ],
                    "owner" => "urn:li:person:" . $request["person_id"],
                    "serviceRelationships" => [
                        [
                            "relationshipType" => "OWNER",
                            "identifier" => "urn:li:userGeneratedContent",
                        ],
                    ],
                ],
            ];
            try {
                $prepareReponse = $this->curl(
                    $prepareUrl,
                    json_encode($prepareRequest),
                    "application/json"
                );
                if (isset(json_decode($prepareReponse)->value)) {
                    $uploadURL = json_decode($prepareReponse)->value
                        ->uploadMechanism
                        ->{"com.linkedin.digitalmedia.uploading.MediaUploadHttpRequest"}
                        ->uploadUrl;
                    $asset_id = json_decode($prepareReponse)->value->asset;
                    Log::info($asset_id);
                    $destinationPath = public_path("uploads/images");
                    // if (!File::isDirectory($destinationPath)) {
                    //     File::makeDirectory(
                    //         $destinationPath,
                    //         0777,
                    //         true,
                    //         true
                    //     );
                    // }
                    // $file = $request->file("files")[0]; // get the first uploaded file
                    // $imagePath =
                    //     date("YmdHisv") .
                    //     rand(1, 1000000) .
                    //     "." .
                    //     $file->getClientOriginalExtension();
                    // if ($file->move($destinationPath, $imagePath)) {
                    //     $imageArr["name"] = $imagePath;
                    //     $imageArr["path"] = "uploads/images";
                    //     $imageArr[
                    //         "extension"
                    //     ] = $file->getClientOriginalExtension();
                    // }
                    $imageArr["path"] = "uploads/images";
                    $imagePathUrl =
                        $imageArr["path"] . "/" . $imagePath[0];
                    Log::info($imagePathUrl);
                    $content = file_get_contents($imagePathUrl);
                    $registerUpload = Http::timeout(1200)
                        ->withHeaders([
                            "Authorization" =>
                            "Bearer " . $request["linkedInToken"],
                        ])
                        ->attach("file", $content, $imagePathUrl)
                        ->put($uploadURL)
                        ->json();
                    Log::info($registerUpload);
                }
                // else {
                //     throw new Exception("Unauthorized Access!");
                // }
            } catch (Exception $e) {
                return response()->json(
                    ["message" => $e->getMessage()],
                    JsonResponse::HTTP_UNAUTHORIZED // Set the desired status code here
                )->getStatusCode();
            }
            $contentEntities = [];
            $requestparam = [
                "author" => "urn:li:person:" . $request["person_id"],
                "lifecycleState" => "PUBLISHED",
                "specificContent" => [
                    "com.linkedin.ugc.ShareContent" => [
                        "media" => [
                            [
                                "media" => $asset_id,
                                "status" => "READY",
                            ],
                        ],
                        "shareCommentary" => [
                            "text" => !empty($request["text"])
                                ? $request["text"]
                                : "",
                        ],
                        "shareMediaCategory" => "VIDEO",
                    ],
                ],
                "visibility" => [
                    "com.linkedin.ugc.MemberNetworkVisibility" => "PUBLIC",
                ],
            ];

            $registerUpload = Http::withHeaders([
                "Authorization" => "Bearer " . $request["linkedInToken"],
            ])
                ->post(
                    "https://api.linkedin.com/v2/ugcPosts",
                    $requestparam
                )
                ->json();
        }
        return $registerUpload;
    }

    public function uploadPostOnInstagram($request, $imagePath)
    {
        if ($request["files"]) {
            if (count($request["files"]) <= 1) {
                $destinationPath = public_path("uploads/images");
                // if (!File::isDirectory($destinationPath)) {
                //     File::makeDirectory($destinationPath, 0777, true, true);
                // }
                // if (!isset($imageArr)) {
                //     $file = $imagePath[0]; // get the first uploaded file
                //     $fileNameWithExtension = str_replace(
                //         ["-", " ", "(", ")", "%", "#", "$", "*", "&", "[", "]", "+"],
                //         "",
                //         $file->getClientOriginalName()
                //     );
                //     // dd($fileNameWithExtension);
                //     // $imagePath = date('YmdHisv') . rand(1, 100000000) . $fileNameWithExtension;

                //     if (
                //         $file->move(
                //             $destinationPath,
                //             $fileNameWithExtension
                //         )
                //     ) {
                //         $imageArr["name"] = $fileNameWithExtension;
                //         $imageArr["path"] = "uploads/images";
                //         $imageArr[
                //             "extension"
                //         ] = $file->getClientOriginalExtension();
                //     }
                // }
                $extension = pathinfo($imagePath[0], PATHINFO_EXTENSION);
                if (
                    $extension == "mp4" ||
                    $extension == "mov" || $extension == "gif"
                ) {
                    try {
                        $imageUrl = asset('uploads/images/' . $imagePath[0]);
                        // $imageUrl =
                        //     "https://social-media-api.tecmantras.com/uploads/images/20230905061741000507650.mp4";
                            if (!$imageUrl) {
                            throw new LinkedInFailException("Unauthorized Access!");
                        }
                        $maxAttempts = 10;
                        $attempt = 1;
                        $timeout = 5; // Timeout in seconds
                        $shareVideoId = null;

                        try {
                            $response = Http::withHeaders([
                                "Authorization" => "Bearer " . $request["instragramToken"],
                            ])->timeout($timeout)->post(
                                "https://graph.facebook.com/v16.0/17841458397296954/media",
                                [
                                    "media_type" => "VIDEO",
                                    "video_url" => $imageUrl,
                                    "caption" => $request["caption"],
                                    "is_carousel_item" => false,
                                ]
                            );
                            $statusCode = $response->getStatusCode();
                            if ($statusCode === 200 || $statusCode === 201) {
                                $shareVideo = $response->json();
                                $shareVideoId = $shareVideo["id"];
                                while ($attempt <= $maxAttempts) {
                                    try {
                                        $response = Http::withHeaders([
                                            "Authorization" => "Bearer " . $request["instragramToken"],
                                        ])->post(
                                            "https://graph.facebook.com/v16.0/17841458397296954/media_publish",
                                            [
                                                "creation_id" => $shareVideoId,
                                            ]
                                        );
                                        $shareVideo = $response->json();
                                        $statusCode = $response->getStatusCode();
                                        if ($statusCode === 200 || $statusCode === 201) {
                                            $shareVideo = $response;
                                            $registerUpload = $shareVideo;
// dd($registerUpload);
                                            break;
                                        } else {
                                            // Handle other status codes or error conditions if needed
                                            // You can log or throw an exception here
                                            // For now, we will retry the request
                                            $attempt++;
                                            sleep(7); // Wait for 5 seconds before retrying
                                        }
                                    } catch (Exception $e) {

                                        $attempt++;
                                        sleep(7); // Wait for 5 seconds before retrying
                                        return response()->json(
                                            ["message" => $e->getMessage()],
                                            JsonResponse::HTTP_UNAUTHORIZED // Set the desired status code here
                                        )->getStatusCode();
                                    }
                                }
                            } else {
                                return response()->json(
                                    ["message" => $response->json(), 'status' => JsonResponse::HTTP_UNAUTHORIZED],
                                    JsonResponse::HTTP_UNAUTHORIZED // Set the desired status code here
                                );
                            }
                        } catch (Exception $e) {
                            return response()->json(
                                ["message" => $response->json(), 'status' => JsonResponse::HTTP_UNAUTHORIZED],
                                JsonResponse::HTTP_UNAUTHORIZED // Set the desired status code here
                            );
                        }

                        // $statusCode = $response->getStatusCode();
                        // Log::info($statusCode);
                        // if ($statusCode != 200 && $statusCode != 201) {
                        //     throw new Exception(
                        //         "Video publish failed. HTTP response code: " .
                        //             $statusCode
                        //     );
                        // }


                        // dd($shareVideoId);
                    } catch (Exception $e) {
                        return response()->json(
                            ["message" => $response->json(), 'status' => JsonResponse::HTTP_UNAUTHORIZED],
                            JsonResponse::HTTP_UNAUTHORIZED // Set the desired status code here
                        );
                    }
                } else {
                    $imageUrl = asset('uploads/images/' . $imagePath[0]);

                    // $imageUrl =
                    //     "https://ik.imagekit.io/ikmedia/backlit.jpg";
                    if ($imageUrl) {
                        $shareImage = Http::withHeaders([
                            "Authorization" =>
                            "Bearer " . $request["instragramToken"],
                        ])
                            ->post(
                                "https://graph.facebook.com/v16.0/17841458397296954/media?image_url=" .
                                    $imageUrl .
                                    "&is_carousel_item=false&caption=" .
                                    $request["caption"]
                            );
                    } else {
                        return response()->json(
                            ["message" => $imageUrl, 'status' => JsonResponse::HTTP_UNAUTHORIZED],
                            JsonResponse::HTTP_UNAUTHORIZED // Set the desired status code here
                        );
                    }
                    if (isset($shareImage["id"])) {
                        $registerUpload = Http::withHeaders([
                            "Authorization" =>
                            "Bearer " . $request["instragramToken"],
                        ])
                            ->post(
                                "https://graph.facebook.com/v16.0/17841458397296954/media_publish?creation_id=" .
                                    $shareImage["id"]
                            );
                    } else {
                        return response()->json(
                            ["message" => $shareImage, 'status' => JsonResponse::HTTP_UNAUTHORIZED],
                            JsonResponse::HTTP_UNAUTHORIZED // Set the desired status code here
                        );
                    }
                }
            } elseif (count($request["files"]) > 1) {
                $destinationPath = public_path("uploads/images/register");
                foreach ($imagePath as $key => $image) {
                    // $imagePath =
                    //     date("YmdHisv") .
                    //     rand(1, 10000) .
                    //     "." .
                    //     $image->getClientOriginalExtension();
                    //     dd($imagePath);
                    // if ($image->move($destinationPath, $imagePath)) {
                    //     $imageArr["name"] = $imagePath;
                    //     $imageArr["path"] = "uploads/images/register";
                    //     $imageArr[
                    //         "extension"
                    //     ] = $image->getClientOriginalExtension();
                    // }
                    $extension = pathinfo($image, PATHINFO_EXTENSION);
                    if (
                        $extension == "mp4" ||
                        $extension == "mov" || $extension == "gif"
                    ) {
                        $imageUrl = asset('uploads/images/' . $image);
                        // $imageUrl =
                        //     "https://social-media-api.tecmantras.com/uploads/images/20230509115334000519093.mp4";
                        $uploadVideo = Http::withHeaders([
                            "Authorization" =>
                            "Bearer " . $request["instragramToken"],
                        ])
                            ->post(
                                "https://graph.facebook.com/v16.0/17841458397296954/media?media_type=VIDEO&video_url=" .
                                    $imageUrl .
                                    "&is_carousel_item=true"
                            );
                        $shareImage[] = $uploadVideo->json();
                    } else {
                        $imageUrl = asset('uploads/images/' . $image);
                        // $imageUrl =
                        //     "https://ik.imagekit.io/ikmedia/backlit.jpg";
                        $uploadImage = Http::withHeaders([
                            "Authorization" =>
                            "Bearer " . $request["instragramToken"],
                        ])
                            ->post(
                                "https://graph.facebook.com/v16.0/17841458397296954/media?image_url=" .
                                    $imageUrl .
                                    "&is_carousel_item=true&caption=" .
                                    $request["caption"]
                            );
                        $shareImage[] = $uploadImage->json();
                    }
                    // $imageUrl = asset('uploads/images/' . $imageArr['name']);
                }
                $idValues = array_column($shareImage, "id");
                $idString = implode(",", $idValues);
                $maxAttempts = 15;
                $attempt = 1;
                $timeout = 12; // Timeout in seconds
                // $registerUpload = null;
                while ($attempt <= $maxAttempts) {
                    try {
                        $registerUploadmultipleFile = Http::withHeaders([
                            "Authorization" => "Bearer " . $request["instragramToken"],
                        ])->post(
                            "https://graph.facebook.com/v16.0/17841458397296954/media?caption=" .
                                $request["caption"] .
                                "&media_type=CAROUSEL&children=" .
                                $idString
                        );
                        $registerUploadmultiple = '';
                        $statusCode = $registerUploadmultipleFile->getStatusCode();
                        log::info(['registerUploadmultipleFile' => $registerUploadmultipleFile->json()]);
                        if ($statusCode === 200 || $statusCode === 201) {
                            $registerUploadmultiple = $registerUploadmultipleFile->json();
                            log::info($registerUploadmultiple);
                            break;
                        } else {
                            $attempt++;
                            sleep(7); // Wait for 5 seconds before retrying
                        }
                    } catch (Exception $e) {
                        return response()->json(
                            ["message" => $shareImage, 'status' => JsonResponse::HTTP_UNAUTHORIZED],
                            JsonResponse::HTTP_UNAUTHORIZED // Set the desired status code here
                        );
                    }
                }
                while ($attempt <= $maxAttempts) {
                    try {
                        $registerUploadVideo = Http::withHeaders([
                            "Authorization" => "Bearer " . $request["instragramToken"],
                        ])->post(
                            "https://graph.facebook.com/v16.0/17841458397296954/media_publish?creation_id=" .
                                $registerUploadmultiple["id"]
                        );
                        $statusCode = $registerUploadVideo->getStatusCode();
                        $registerUpload = $registerUploadVideo->json();
                        log::info($registerUpload);
                        if ($statusCode === 200 || $statusCode === 201) {
                            $registerUpload = $registerUploadVideo;
                            break;
                        } else {
                            // Handle other status codes or error conditions if needed
                            // You can log or throw an exception here
                            // For now, we will retry the request
                            $attempt++;
                            sleep(7); // Wait for 5 seconds before retrying
                        }
                    } catch (Exception $e) {
                        return response()->json(
                            ["message" => $e, 'status' => JsonResponse::HTTP_UNAUTHORIZED],
                            JsonResponse::HTTP_UNAUTHORIZED // Set the desired status code here
                        );
                    }
                }
                // while ($attempt <= $maxAttempts) {
                //         // dd($registerUploadmultiple["id"]);
                //         log::info($registerUploadmultiple["id"]);
                //         $registerUploadVideo = '';   
                //         if (isset($registerUploadmultiple["id"]) && $registerUploadmultiple["id"] != 0) {
                //             $registerUploadVideo = Http::withHeaders([
                //                 "Authorization" => "Bearer " . $request["instragramToken"],
                //             ])->post(
                //                 "https://graph.facebook.com/v16.0/17841458397296954/media_publish?creation_id=" .
                //                 $registerUploadmultiple["id"]
                //             )->json();
                //             break; // Exit the while loop on successful response
                //         } else {
                //             // Handle other status codes or error conditions if needed
                //             // You can log or throw an exception here
                //             // For now, we will retry the request
                //             $attempt++;
                //             sleep(5); // Wait for 5 seconds before retrying
                //         }
                //         $statusCode = $registerUploadVideo->getStatusCode();
                //         if ($statusCode === 200 || $statusCode === 201) {
                //             $registerUpload = $registerUploadVideo->json();
                //             // dd($registerUploadmultiple);
                //             // $registerUpload = $registerUploadmultiple;
                //             // dd($registerUpload);
                //             break;
                //         } else {
                //             // Handle other status codes or error conditions if needed
                //             // You can log or throw an exception here
                //             // For now, we will retry the request
                //             $attempt++;
                //             sleep(7); // Wait for 5 seconds before retrying
                //         }
                // }
            }
        }
        return $registerUpload;
    }

    public function uploadPostOnFacebook($request, $imagePath)
    {
        // Adding Medias
        $media = [];
        // log::info($request->all());
        foreach ($request['facebookId'] as $facebookId) {
            if ($request["type"] == "image") {
                if (count($imagePath) <= 1) {
                    try {
                        $imageArr["path"] = "uploads/images";
                        $imagePathUrl = $imageArr["path"] . "/" . $imagePath[0];

                        $requestData = [
                            'message' => $request['text']
                        ];

                        $imageData = file_get_contents($imagePathUrl);
                        $imageUpload = Http::withHeaders([
                            "Authorization" =>
                            "Bearer " . $facebookId['access_token'],
                        ])
                            ->attach("file", $imageData, $imagePathUrl)
                            ->post("https://graph.facebook.com/v16.0/" . $facebookId['id'] . "/photos", $requestData);

                        if ($imageUpload->getStatusCode() == 201 || $imageUpload->getStatusCode() == 200) {
                            $registerUpload = $imageUpload;
                        } else {
                            return response()->json(
                                ["message" => $imageUpload->json(), 'status' => JsonResponse::HTTP_UNAUTHORIZED],
                                JsonResponse::HTTP_UNAUTHORIZED // Set the desired status code here
                            );
                        }
                    } catch (Exception $e) {
                        // Handle the exception here
                        // Log the error message or return an error response to the user
                        return response()->json(
                            ["message" => $e->getMessage(), 'status' => JsonResponse::HTTP_UNAUTHORIZED],
                            JsonResponse::HTTP_UNAUTHORIZED // Set the desired status code here
                        );
                    }
                } else {
                    foreach ($imagePath as $key => $image) {
                        try {
                            $imageArr["path"] = "uploads/images";
                            $imagePathUrl = $imageArr["path"] . "/" . $image;

                            $requestData = [
                                'message' => $request['text'],
                                'published' => false
                            ];

                            $imageData = file_get_contents($imagePathUrl);
                            $uploadImageFile = Http::withHeaders([
                                "Authorization" =>
                                "Bearer " . $facebookId['access_token'],
                            ])
                                ->attach("file", $imageData, $imagePathUrl)
                                ->post("https://graph.facebook.com/v16.0/" . $facebookId['id'] . "/photos", $requestData);

                            if ($uploadImageFile->getStatusCode() == 201 || $uploadImageFile->getStatusCode() == 200) {
                                $uploadImage[] = $uploadImageFile;
                            } else {
                                return response()->json(
                                    ["message" => $uploadImageFile->json(), 'status' => JsonResponse::HTTP_UNAUTHORIZED],
                                    JsonResponse::HTTP_UNAUTHORIZED // Set the desired status code here
                                );
                            }
                            // $registerUpload[] = $uploaImage['id'];
                        } catch (Exception $e) {
                            // Handle the exception here
                            // Log the error message or return an error response to the user
                            return response()->json(
                                ["message" => $e->getMessage(), 'status' => JsonResponse::HTTP_UNAUTHORIZED],
                                JsonResponse::HTTP_UNAUTHORIZED // Set the desired status code here
                            );
                        }
                    }
                    foreach ($uploadImage as $item) {
                        $convertedArray[] = [
                            "media_fbid" => $item["id"]
                        ];
                    }
                    try {
                        $requestData = [
                            'message' => $request['text'],
                            'attached_media' => $convertedArray
                        ];
                        $UploadImage = Http::withHeaders([
                            "Authorization" => "Bearer " . $facebookId['access_token'],
                        ])->post(
                            "https://graph.facebook.com/v16.0/" . $facebookId['id'] . "/feed",
                            $requestData
                        );

                        if ($uploadImageFile->getStatusCode() == 201 || $uploadImageFile->getStatusCode() == 200) {
                            $registerUpload = $UploadImage;
                        } else {
                            return response()->json(
                                ["message" => $uploadImageFile->json(), 'status' => JsonResponse::HTTP_UNAUTHORIZED],
                                JsonResponse::HTTP_UNAUTHORIZED // Set the desired status code here
                            );
                        }
                    } catch (Exception $e) {
                        // Handle request exceptions
                        // You can log or throw an exception here
                        // For now, we will retry the request
                        return response()->json(
                            ["message" => $e->getMessage(), 'status' => JsonResponse::HTTP_UNAUTHORIZED],
                            JsonResponse::HTTP_UNAUTHORIZED // Set the desired status code here
                        );
                    }
                }
            }
            if ($request["type"] == "text") {
                try {
                    $requestparam = [
                        "message" => $request['text'],
                    ];
                    $textUpload = Http::withHeaders([
                        "Authorization" => "Bearer " . $facebookId["access_token"],
                    ])
                        ->post(
                            "https://graph.facebook.com/v16.0/" . $facebookId['id'] . "/feed",
                            $requestparam
                        );
                    if ($textUpload->getStatusCode() == 201 || $textUpload->getStatusCode() == 200) {
                        $registerUpload = $textUpload;
                    } else {
                        return response()->json(
                            ["message" => $textUpload->json(), 'status' => JsonResponse::HTTP_UNAUTHORIZED],
                            JsonResponse::HTTP_UNAUTHORIZED // Set the desired status code here
                        );
                    }
                } catch (Exception $e) {
                    // Handle request exceptions
                    // You can log or throw an exception here
                    // For now, we will retry the request
                    return response()->json(
                        ["message" => $e->getMessage(), 'status' => JsonResponse::HTTP_UNAUTHORIZED],
                        JsonResponse::HTTP_UNAUTHORIZED // Set the desired status code here
                    );
                }
            }

            if ($request["type"] == "video") {
                try {

                    $requestparam = [
                        "description" => $request['text'],
                    ];

                    $imageArr["path"] = "uploads/images";
                    $imagePathUrl = $imageArr["path"] . "/" . $imagePath[0];
                    
                    $imageData = file_get_contents($imagePathUrl);
                    // dd("https://graph.facebook.com/v16.0/" . $facebookId['id'] . "/videos");
                    $uploadVideoFile = Http::withHeaders([
                        "Authorization" => "Bearer " . $facebookId["access_token"],
                        // Add more headers as needed
                    ])
                        ->attach("file", $imageData, $imagePathUrl)
                        ->post("https://graph.facebook.com/v16.0/" . $facebookId['id'] . "/videos", $requestparam);
                    // dd($uploadVideoFile->getStatusCode());
                    if ($uploadVideoFile->getStatusCode() == 200) {
                        $registerUpload = $uploadVideoFile;
                    } else {
                        return response()->json(
                            ["message" => $uploadVideoFile->json(), 'status' => JsonResponse::HTTP_UNAUTHORIZED],
                            JsonResponse::HTTP_UNAUTHORIZED // Set the desired status code here
                        );
                    }
                } catch (Exception $e) {
                    // Handle request exceptions
                    // You can log or throw an exception here
                    // For now, we will retry the request
                    return response()->json(
                        ["message" => $e->getMessage(), 'status' => JsonResponse::HTTP_UNAUTHORIZED],
                        JsonResponse::HTTP_UNAUTHORIZED // Set the desired status code here
                    );
                }
            }
        }
        return $registerUpload;
    }

    public function uploadPostOnTwitter($request, $imagePath)
    {
        if ($request["type"] == "image") {

            foreach ($imagePath as $key => $image) {
                try {
                    $requestData = [
                        'message' => $request['text'],
                        'published' => false
                    ];
                    $imageArr["path"] = "uploads/images";
                    $imagePathUrl = $imageArr["path"] . "/" . $image;

                    $requestData = [
                        'message' => $request['text'],
                        'published' => false
                    ];

                    $imageData = file_get_contents($imagePathUrl);

                    $imageUrl = asset('uploads/images/' . $image);
                    $oauthConsumerKey = "MstSMK2dCJsa78O2Owlb80P3M";
                    $oauthToken = $request['access_token'];
                    $oauthSignatureMethod = "HMAC-SHA1";
                    $oauthTimestamp = "1684998283";
                    $oauthNonce = "Cti5OffHFZu";
                    $oauthVersion = "1.0";
                    $oauthSignature = "bOjCbRcCRA%2Bxjd8f9%2Fm4Sb5cfV0%3D";

                    $guestId = "v1%3A168492609637678520";
                    $guestIdAds = "v1%3A168492609637678520";
                    $guestIdMarketing = "v1%3A168492609637678520";
                    $personalizationId = "v1_nCLwX8fEC4gb5QdCZFFrWQ==";

                    $headers = [
                        'Authorization' => 'OAuth oauth_consumer_key="' . $oauthConsumerKey . '",oauth_token="' . $oauthToken . '",oauth_signature_method="' . $oauthSignatureMethod . '",oauth_timestamp="' . $oauthTimestamp . '",oauth_nonce="' . $oauthNonce . '",oauth_version="' . $oauthVersion . '",oauth_signature="' . $oauthSignature . '"',
                        'Cookie' => 'guest_id=' . $guestId . '; guest_id_ads=' . $guestIdAds . '; guest_id_marketing=' . $guestIdMarketing . '; personalization_id="' . $personalizationId . '"'
                    ];

                    $uploadImageFile = Http::withHeaders($headers)->attach(
                        'media',
                        $imageData,
                        '<Content-type header>',
                        ['media_category' => 'tweet_image']
                    )->post('https://upload.twitter.com/1.1/media/upload.json');

                    if ($uploadImageFile->getStatusCode() == 201 || $uploadImageFile->getStatusCode() == 200) {
                        $uploadImage[] = $uploadImageFile->json();
                    } else {
                        return response()->json(
                            ["message" => $uploadImageFile->json(), 'status' => JsonResponse::HTTP_UNAUTHORIZED],
                            JsonResponse::HTTP_UNAUTHORIZED // Set the desired status code here
                        );
                    }
                    // $registerUpload[] = $uploaImage['id'];
                } catch (Exception $e) {
                    // Handle the exception here
                    // Log the error message or return an error response to the user
                    return response()->json(
                        ["message" => $e->getMessage(), 'status' => JsonResponse::HTTP_UNAUTHORIZED],
                        JsonResponse::HTTP_UNAUTHORIZED // Set the desired status code here
                    );
                }
            }
            $idValues = array_column($uploadImage, "media_id");
            $idString = implode(",", $idValues);
            try {
                $oauthConsumerKey = "MstSMK2dCJsa78O2Owlb80P3M";
                $oauthToken =  $request['access_token'];
                $oauthSignatureMethod = "HMAC-SHA1";
                $oauthTimestamp = "1685004900";
                $oauthNonce = "zsydbQ64wqx";
                $oauthVersion = "1.0";
                $oauthSignature = "zk0mZjjCAPaqS1bNUqJMTT1tPXM%3D";

                $guestId = "v1%3A168492609637678520";
                $guestIdAds = "v1%3A168492609637678520";
                $guestIdMarketing = "v1%3A168492609637678520";
                $personalizationId = "v1_nCLwX8fEC4gb5QdCZFFrWQ==";

                $headers = [
                    'Authorization' => 'OAuth oauth_consumer_key="' . $oauthConsumerKey . '",oauth_token="' . $oauthToken . '",oauth_signature_method="' . $oauthSignatureMethod . '",oauth_timestamp="' . $oauthTimestamp . '",oauth_nonce="' . $oauthNonce . '",oauth_version="' . $oauthVersion . '",oauth_signature="' . $oauthSignature . '"',
                    'Content-Type' => 'application/json',
                    'Cookie' => 'guest_id=' . $guestId . '; guest_id_ads=' . $guestIdAds . '; guest_id_marketing=' . $guestIdMarketing . '; personalization_id="' . $personalizationId . '"',
                ];

                $requestData = [
                    "text" => $request['text'],
                    "media" => [
                        "media_ids" => explode(",", $idString)
                    ]
                ];

                // $requestData = json_encode($requestData;

                $uploadImageFiles = Http::withHeaders($headers)
                    ->post('https://api.twitter.com/2/tweets', $requestData);
                if ($uploadImageFiles->getStatusCode() == 201 || $uploadImageFiles->getStatusCode() == 200) {
                    $registerUpload = $uploadImageFiles;
                } else {
                    return response()->json(
                        ["message" => $uploadImageFile->json(), 'status' => JsonResponse::HTTP_UNAUTHORIZED],
                        JsonResponse::HTTP_UNAUTHORIZED // Set the desired status code here
                    );
                }
            } catch (Exception $e) {
                // Handle request exceptions
                // You can log or throw an exception here
                // For now, we will retry the request
                return response()->json(
                    ["message" => $e->getMessage(), 'status' => JsonResponse::HTTP_UNAUTHORIZED],
                    JsonResponse::HTTP_UNAUTHORIZED // Set the desired status code here
                );
            }
        }
        if ($request["type"] == "text") {
            try {
                $requestparam = [
                    "text" => $request['text'],
                ];
                $oauthConsumerKey = "FSbbVOpChsRoXjcAmx5o25c8S";
                $oauthToken = $request['access_token'];
                $oauthSignatureMethod = "HMAC-SHA1";
                $oauthTimestamp = "1685682116";
                $oauthNonce = "JPsMslQKS7T";
                $oauthVersion = "1.0";
                $oauthSignature = "aroZ6cZlrml0jvepzu7nU6zTUF8%3D";
                $headers = [
                    'Authorization' => 'OAuth oauth_consumer_key="' . $oauthConsumerKey . '",oauth_token="' . $oauthToken . '",oauth_signature_method="' . $oauthSignatureMethod . '",oauth_timestamp="' . $oauthTimestamp . '",oauth_nonce="' . $oauthNonce . '",oauth_version="' . $oauthVersion . '",oauth_signature="' . $oauthSignature . '"',
                    'Content-Type' => 'application/json',
                    'Cookie' => 'guest_id=v1%3A168492609637678520; guest_id_ads=v1%3A168492609637678520; guest_id_marketing=v1%3A168492609637678520; personalization_id="v1_nCLwX8fEC4gb5QdCZFFrWQ=="'
                ];

                $textUpload = Http::withHeaders($headers)->post('https://api.twitter.com/2/tweets', $requestparam)->json();
                dd($textUpload);
                if ($textUpload->getStatusCode() == 201 || $textUpload->getStatusCode() == 200) {
                    $registerUpload = $textUpload;
                } else {
                    return response()->json(
                        ["message" => $textUpload->json(), 'status' => JsonResponse::HTTP_UNAUTHORIZED],
                        JsonResponse::HTTP_UNAUTHORIZED // Set the desired status code here
                    );
                }
            } catch (Exception $e) {
                // Handle request exceptions
                // You can log or throw an exception here
                // For now, we will retry the request
                return response()->json(
                    ["message" => $e->getMessage(), 'status' => JsonResponse::HTTP_UNAUTHORIZED],
                    JsonResponse::HTTP_UNAUTHORIZED // Set the desired status code here
                );
            }
        }
        if ($request["type"] == "video") {

            $oauthConsumerKey = "FSbbVOpChsRoXjcAmx5o25c8S";
            $oauthToken = $request['access_token'];
            $oauthSignatureMethod = "HMAC-SHA1";
            $oauthTimestamp = "1685681820";
            $oauthNonce = "mkE3R0kUZZd";
            $oauthVersion = "1.0";
            $oauthSignature = "24c2II%2BbLE0%2Bqb50iu3FbLwhrUA%3D";

            $guestId = "v1%3A168492609637678520";
            $guestIdAds = "v1%3A168492609637678520";
            $guestIdMarketing = "v1%3A168492609637678520";
            $personalizationId = "v1_nCLwX8fEC4gb5QdCZFFrWQ==";
            $requestparam = [
                "command" => "INIT",
                "total_bytes" => $request['total_bytes'],
                "media_type" => "video/mp4",
                "media_category" => "tweet_video"
            ];

            $headers = [
                'Authorization' => 'OAuth oauth_consumer_key="' . $oauthConsumerKey . '",oauth_token="' . $oauthToken . '",oauth_signature_method="' . $oauthSignatureMethod . '",oauth_timestamp="' . $oauthTimestamp . '",oauth_nonce="' . $oauthNonce . '",oauth_version="' . $oauthVersion . '",oauth_signature="' . $oauthSignature . '"',
                'Cookie' => 'guest_id=' . $guestId . '; guest_id_ads=' . $guestIdAds . '; guest_id_marketing=' . $guestIdMarketing . '; personalization_id="' . $personalizationId . '"'
            ];

            $uploadImageFile = Http::withHeaders($headers)->post('https://upload.twitter.com/1.1/media/upload.json?command=INIT&total_bytes=2848208&media_type=video/mp4&media_category=tweet_video', $requestparam)->json();
        }
        return $registerUpload;
    }

    public function moveFileToDestination($image)
    {
        $imagePath = date("YmdHisv") . rand(1, 10000) . "." . $image->getClientOriginalExtension();
        $destinationPath = "uploads/images";
        if (!File::isDirectory($destinationPath)) {
            File::makeDirectory(
                $destinationPath,
                0777,
                true,
                true
            );
        }
        // Move the image to the destination folder
        if ($image->move($destinationPath, $imagePath)) {
            return $imagePath;
        }

        return null;
    }
}
