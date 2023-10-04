<?php

namespace App\Http\Controllers;

use App\Http\Requests\SocialMediaPayloadRequest;
use App\Http\Response\CustomApiResponse;
use App\Models\SocialMediaPayload;
use App\Repositories\SocialMediaRepository;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LinkedInController extends Controller
{
    protected $app_id;
    protected $app_secret;
    protected $callback;
    protected $scopes;
    protected $ssl;
    protected $apiResponse;
    protected $socialMediaRepository;


    // public function __construct(bool $ssl = true)
    // {
    //     $this->ssl = $ssl;
    // }
    public function __construct(
        CustomApiResponse $customApiResponse,
        SocialMediaRepository $socialMediaRepository
    ) {
        $this->apiResponse = $customApiResponse;
        $this->socialMediaRepository = $socialMediaRepository;
    }

    public function linkedinGenerateToken(Request $request)
    {
        // https://socialsync.tecmantras.com/
        return Http::post(
            "https://www.linkedin.com/oauth/v2/accessToken?grant_type=authorization_code&code=" .
                $request["code"] .
                "&client_id=7704luv7yvpyll&client_secret=DX6hyqMB6rjnCvEO&redirect_uri=https://socialsync.tecmantras.com/linkedin"
        )->json();
        // return [
        //     'access_token' => $generateAccessToken['access_token'],
        // ];
    }

    public function getUserDetail()
    {
        $setVeerifyToken = Http::withHeaders([
            "Authorization" => "Bearer " . request()->bearerToken(),
        ])
            ->get("https://api.linkedin.com/v2/me")
            ->json();

        $response = Http::withHeaders([
            "Authorization" => "Bearer " . request()->bearerToken(),
        ])->get(
            "https://api.linkedin.com/v2/me?projection=(profilePicture(displayImage~:playableStreams~identifiers))"
        );

        $setVerifyToken = $response->json();
        $setVeerifyToken["profile"] = [];
        $setVeerifyToken["profile"] = !empty($setVerifyToken["profilePicture"]["displayImage~"]["elements"][0]["identifiers"][0]["identifier"])
            ? $setVerifyToken["profilePicture"]["displayImage~"]["elements"][0]["identifiers"][0]["identifier"]
            : "";
        return $setVeerifyToken;
    }

    public function registerUpload(Request $request)
    {
        $post_data = [
            "registerUploadRequest" => [
                "recipes" => ["urn:li:digitalmediaRecipe:feedshare-image"],
                "owner" => "urn:li:person:" . $request["owner_id"],
                "serviceRelationships" => [
                    [
                        "relationshipType" => "OWNER",
                        "identifier" => "urn:li:userGeneratedContent",
                    ],
                ],
            ],
        ];
        $registerUpload = Http::withHeaders([
            "LinkedIn-Version" => "202206",
            "Authorization" => "Bearer " . request()->bearerToken(),
        ])
            ->post(
                "https://api.linkedin.com/rest/assets?action=registerUpload",
                $post_data
            )
            ->json();

        return $registerUpload;
    }

    public function getIntagramProfile()
    {
        return Http::withHeaders([
            "Authorization" => "Bearer " . request()->bearerToken(),
        ])->get(
            "https://graph.facebook.com/v16.0/17841458397296954?fields=profile_picture_url"
        )->json();
    }

    public function uploadImage(Request $request)
    {
        $registerUpload = Http::withHeaders([
            "LinkedIn-Version" => "202206",
            "Authorization" => "Bearer " . request()->bearerToken(),
        ])
            ->post($request["upload_url"], $request["file"])
            ->json();

        return $registerUpload;
    }

    public function imageShare(Request $request)
    {
        try {
            $user = $this->socialMediaRepository->imageShare($request);
            $statusCode = $user->getStatusCode();
            if ($statusCode === 200 || $statusCode === 201) {
                $success = [
                    "user" => $user->json(),
                ];
                $message = "Post uploaded successfully";

                return $this->apiResponse->getResponseStructure(true, $success, $message);
            } else {
                return response()->json(
                    $user->original,
                    JsonResponse::HTTP_UNAUTHORIZED // Set the desired status code here
                );
                // return response()->json($user->original);
            }
        } catch (Exception $e) {
            Log::info($e);
            return response()->json(
                ["message" => $e->getMessage(), 'status' => 500],
                500
            );
        }
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

    public function uploadSchedulePost(SocialMediaPayloadRequest $request)
    {
        try {
            $user = $this->socialMediaRepository->uploadSchedulePost($request);

            if (!empty($user)) {
                $success = [
                    $user
                ];
                $message = "Your post will be updated within 5 minutes of your selected time";

                return $this->apiResponse->getResponseStructure(TRUE, $success, $message);
            }
        } catch (Exception $e) {
            log::info($e);
            return $this->apiResponse->handleAndResponseException($e);
        }
    }


    public function uploadpostOnSocialMedia()
    {
        $registerUpload = "";
        $datetime = Carbon::now()->setTimezone('Asia/Kolkata')->format('Y-m-d H:i');
        $getpayloads = SocialMediaPayload::where('upload_time', '<=', $datetime)->where(['upload_post_status' => '1'])->get();
        // $getpayloads = SocialMediaPayload::where(['upload_post_status' => '1'])->get();
        if (count($getpayloads) > 0) {
            foreach ($getpayloads as $getpayload) {
                $payload = json_decode($getpayload["payload"]);
                if (isset($payload->files)) {
                    $files = explode(',', $payload->files);
                }
                // Check if "LinkedIn" is present in the array
                if (in_array("LinkedIn", (array)$payload->socicalType)) {
                    // Posting
                    $post_url =
                        "https://api.linkedin.com/rest/assets?action=registerUpload?oauth2_access_token=" .
                        $payload->linkedInToken;

                    // Adding Medias
                    $media = [];
                    if ($payload->type == "image") {
                        foreach ($files as $key => $image) {
                            // Preparing Request
                            $prepareUrl =
                                "https://api.linkedin.com/v2/assets?action=registerUpload&oauth2_access_token=" .
                                $payload->linkedInToken;
                            $prepareRequest = [
                                "registerUploadRequest" => [
                                    "recipes" => [
                                        "urn:li:digitalmediaRecipe:feedshare-image",
                                    ],
                                    "owner" => "urn:li:person:" . $payload->person_id,
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
                                    $imagePathUrl = $destinationPath . "/" . $image;
                                    $imageName[] = $imagePathUrl;
                                    $imageData = file_get_contents($imagePathUrl);
                                    $registerUpload = Http::withHeaders([
                                        "Authorization" =>
                                        "Bearer " . $payload->linkedInToken,
                                    ])
                                        ->attach("file", $imageData, $imagePathUrl)
                                        ->put($uploadURL)
                                        ->json();
                                }
                                // else {
                                //     log::info("Unauthorized Access!");
                                //     throw new Exception("Unauthorized Access!");
                                // }
                            } catch (Exception $e) {
                                Log::info($e->getMessage());
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
                                "owner" => "urn:li:person:" . $payload->person_id,
                                "text" => [
                                    "text" => !empty($payload->text)
                                        ? $payload->text
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
                                "Bearer " . $payload->linkedInToken,
                            ])
                                ->post(
                                    "https://api.linkedin.com/v2/shares",
                                    $requestparam
                                );
                        } catch (Exception $e) {
                            Log::info($e->getMessage());
                            // Handle the exception here
                            // Log the error message or return an error response to the user
                            return response()->json(
                                ["message" => $e->getMessage()],
                                JsonResponse::HTTP_UNAUTHORIZED // Set the desired status code here
                            )->getStatusCode();
                        }
                    }
                    if ($payload->type == "text") {
                        $requestparam = [
                            "author" => "urn:li:person:" . $payload->person_id,
                            "lifecycleState" => "PUBLISHED",
                            "specificContent" => [
                                "com.linkedin.ugc.ShareContent" => [
                                    "shareCommentary" => [
                                        "text" => $payload->text,
                                    ],
                                    "shareMediaCategory" => "NONE",
                                ],
                            ],
                            "visibility" => [
                                "com.linkedin.ugc.MemberNetworkVisibility" => "PUBLIC",
                            ],
                        ];

                        $registerUpload = Http::withHeaders([
                            "Authorization" => "Bearer " . $payload->linkedInToken,
                        ])
                            ->post(
                                "https://api.linkedin.com/v2/ugcPosts",
                                $requestparam
                            )
                            ->json();
                    }

                    if ($payload->type == "video") {
                        // Preparing Request
                        $prepareUrl =
                            "https://api.linkedin.com/v2/assets?action=registerUpload&oauth2_access_token=" .
                            $payload->linkedInToken;
                        $prepareRequest = [
                            "registerUploadRequest" => [
                                "recipes" => [
                                    "urn:li:digitalmediaRecipe:feedshare-video",
                                ],
                                "owner" => "urn:li:person:" . $payload->person_id,
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
                                $destinationPath = public_path("uploads/images");

                                $imageArr["path"] = "uploads/images";
                                $imagePathUrl = $destinationPath . "/" . $payload->files;
                                $content = file_get_contents($imagePathUrl);
                                $registerUpload = Http::timeout(1200)
                                    ->withHeaders([
                                        "Authorization" =>
                                        "Bearer " . $payload->linkedInToken,
                                    ])
                                    ->attach("file", $content, $imagePathUrl)
                                    ->put($uploadURL)
                                    ->json();
                            }
                            //  else {
                            //     log::info("Unauthorized Access!");
                            //     throw new Exception("Unauthorized Access!");
                            // }
                        } catch (Exception $e) {
                            log::info($e->getMessage());
                            // Handle the exception here
                            // Log the error message or return an error response to the user
                            return response()->json(
                                ["message" => $e->getMessage()],
                                JsonResponse::HTTP_UNAUTHORIZED // Set the desired status code here
                            )->getStatusCode();
                        }
                        $contentEntities = [];
                        $requestparam = [
                            "author" => "urn:li:person:" . $payload->person_id,
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
                                        "text" => !empty($payload->text)
                                            ? $payload->text
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
                            "Authorization" => "Bearer " . $payload->linkedInToken,
                        ])
                            ->post(
                                "https://api.linkedin.com/v2/ugcPosts",
                                $requestparam
                            )
                            ->json();
                    }
                }
                if (in_array("Instagram", (array)$payload->socicalType)) {
                    if ($files) {

                        if (count($files) <= 1) {
                            $destinationPath = public_path("uploads/images");
                            // if (!isset($imageArr)) {
                            //     $file = $request->file("files")[0]; // get the first uploaded file
                            //     $fileNameWithExtension = str_replace(
                            //         ["-", " ", "(", ")", "%", "#", "$", "*", "&", "[", "]", "+"],
                            //         "",
                            //         $file->getClientOriginalName()
                            //     );
                            //     // dd($fileNameWithExtension);
                            //     // $imagePath = date('YmdHisv') . rand(1, 100000000) . $fileNameWithExtension;

                            // }
                            $imageArr["name"] = $files[0];
                            $imageArr["path"] = "uploads/images";
                            $imageArr["extension"] = pathinfo($files[0], PATHINFO_EXTENSION);
                            if (
                                $imageArr["extension"] == "mp4" ||
                                $imageArr["extension"] == "mov" || $imageArr["extension"] == "gif"
                            ) {
                                try {
                                    $imageUrl = asset('uploads/images/' . $imageArr['name']);
                                    // $imageUrl = "https://social-media-api.tecmantras.com/uploads/images/20230905061741000507650.mp4";
                                    if (!$imageUrl) {
                                        log::info("Unauthorized Access!");
                                        throw new Exception("Unauthorized Access!");
                                    }
                                    $maxAttempts = 10;
                                    $attempt = 1;
                                    $timeout = 5; // Timeout in seconds
                                    $shareVideoId = null;
                                    try {
                                        $response = Http::withHeaders([
                                            "Authorization" => "Bearer " . $payload->instragramToken,
                                        ])->timeout($timeout)->post(
                                            "https://graph.facebook.com/v16.0/17841458397296954/media",
                                            [
                                                "media_type" => "VIDEO",
                                                "video_url" => $imageUrl,
                                                "caption" => $payload->caption,
                                                "is_carousel_item" => false,
                                            ]
                                        );
                                        $statusCode = $response->getStatusCode();
                                        if ($statusCode === 200 || $statusCode === 201) {
                                            $shareVideo = $response->json();
                                            $shareVideoId = $shareVideo["id"];
                                            log::info($shareVideoId);
                                            while ($attempt <= $maxAttempts) {
                                                try {
                                                    $response = Http::withHeaders([
                                                        "Authorization" => "Bearer " . $payload->instragramToken,
                                                    ])->post(
                                                        "https://graph.facebook.com/v16.0/17841458397296954/media_publish",
                                                        [
                                                            "creation_id" => $shareVideoId,
                                                        ]
                                                    );
                                                    $shareVideo = $response->json();
                                                    log::info($shareVideo);
                                                    $statusCode = $response->getStatusCode();
                                                    if ($statusCode === 200 || $statusCode === 201) {
                                                        $shareVideo = $response->json();
                                                        $registerUpload = $shareVideo;
                                                        break;
                                                    } else {
                                                        // Handle other status codes or error conditions if needed
                                                        // You can log or throw an exception here
                                                        // For now, we will retry the request
                                                        $attempt++;
                                                        sleep(5); // Wait for 5 seconds before retrying
                                                    }
                                                } catch (Exception $e) {

                                                    $attempt++;
                                                    sleep(5); // Wait for 5 seconds before retrying
                                                    return response()->json(
                                                        ["message" => $e->getMessage()],
                                                        401
                                                    );
                                                }
                                            }
                                        } else {
                                            throw new Exception("Unauthorized Access!");
                                        }
                                    } catch (Exception $e) {
                                        return response()->json(
                                            ["message" => $e->getMessage()],
                                            401
                                        );
                                    }
                                } catch (Exception $e) {
                                    log::info($e->getMessage());
                                    // Handle the exception here
                                    // Log the error message or return an error response to the user
                                    return response()->json(
                                        ["message" => $e->getMessage()],
                                        401
                                    );
                                }
                            } else {
                                $imageUrl = asset('uploads/images/' . $imageArr['name']);
                                // $imageUrl = url('/') . "/uploads/images" . "/" . $imageArr['name'];
                                Log::info($imageUrl);
                                // $imageUrl =
                                //     "https://ik.imagekit.io/ikmedia/backlit.jpg";
                                if ($imageUrl) {
                                    $shareImage = Http::withHeaders([
                                        "Authorization" =>
                                        "Bearer " . $payload->instragramToken,
                                    ])
                                        ->post(
                                            "https://graph.facebook.com/v16.0/17841458397296954/media?image_url=" .
                                                $imageUrl .
                                                "&is_carousel_item=false&caption=" .
                                                $payload->caption
                                        )
                                        ->json();
                                        Log::info($shareImage);
                                } else {
                                    log::info("Unauthorized Access!");
                                    throw new Exception("Unauthorized Access!");
                                }
                                Log::info($shareImage);
                                if (isset($shareImage["id"])) {
                                    $registerUpload = Http::withHeaders([
                                        "Authorization" =>
                                        "Bearer " . $payload->instragramToken,
                                    ])
                                        ->post(
                                            "https://graph.facebook.com/v16.0/17841458397296954/media_publish?creation_id=" .
                                                $shareImage["id"]
                                        )
                                        ->json();
                                } else {
                                    throw new Exception("Unauthorized Access!");
                                }
                                Log::info(["https://graph.facebook.com/v16.0/17841458397296954/media_publish?creation_id=" => $registerUpload]);
                            }
                        } elseif (count($files) > 1) {
                            $destinationPath = public_path("uploads/images");
                            if (isset($imageName)) {
                                foreach ($imageName as $key => $image) {
                                    $imageArr["extension"] = pathinfo($image, PATHINFO_EXTENSION);
                                    if (
                                        $imageArr["extension"] == "mp4" ||
                                        $imageArr["extension"] == "mov" || $imageArr["extension"] == "gif"
                                    ) {
                                        // $imageUrl = asset('uploads/images/' . $image);
                                        $imageUrl = url('/') . "/uploads/images" . "/" . $image;
                                        // $imageUrl = "https://social-media-api.tecmantras.com/uploads/images/20230509115334000519093.mp4";
                                        $shareImage[] = Http::withHeaders([
                                            "Authorization" =>
                                            "Bearer " . $payload->instragramToken,
                                        ])
                                            ->post(
                                                "https://graph.facebook.com/v16.0/17841458397296954/media?media_type=VIDEO&video_url=" .
                                                    $imageUrl .
                                                    "&is_carousel_item=true"
                                            )
                                            ->json();
                                    } else {
                                        // $imageUrl = asset('uploads/images/' . $image);
                                        $imageUrl = url('/') . "/uploads/images" . "/" . $image;
                                        // $imageUrl =
                                            //     "https://ik.imagekit.io/ikmedia/backlit.jpg";
                                        $shareImage[] = Http::withHeaders([
                                            "Authorization" =>
                                            "Bearer " . $payload->instragramToken,
                                        ])
                                            ->post(
                                                "https://graph.facebook.com/v16.0/17841458397296954/media?image_url=" .
                                                    $imageUrl .
                                                    "&is_carousel_item=true&caption=" .
                                                    $payload->caption
                                            )
                                            ->json();
                                        log::info($shareImage);
                                    }
                                    // $imageUrl = asset('uploads/images/' . $imageArr['name']);
                                }
                            } else {
                                foreach ($files as $key => $image) {
                                    $imageArr["name"] = $image;
                                    $imageArr["path"] = "uploads/images";
                                    $imageArr["extension"] = pathinfo($image, PATHINFO_EXTENSION);
                                    if (
                                        $imageArr["extension"] == "mp4" ||
                                        $imageArr["extension"] == "mov" || $imageArr["extension"] == "gif"
                                    ) {
                                        // $imageUrl = asset('uploads/images/' . $image);
                                        $imageUrl = url('/') . "/uploads/images" . "/" . $image;
                                        // $imageUrl = "https://social-media-api.tecmantras.com/uploads/images/20230509115334000519093.mp4";
                                        $uploadVideo = Http::withHeaders([
                                            "Authorization" =>
                                            "Bearer " . $payload->instragramToken,
                                        ])
                                            ->post(
                                                "https://graph.facebook.com/v16.0/17841458397296954/media?media_type=VIDEO&video_url=" .
                                                    $imageUrl .
                                                    "&is_carousel_item=true"
                                            );
                                        $shareImage[] = $uploadVideo->json();
                                    } else {
                                        // $imageUrl = asset('uploads/images/' . $image);
                                        $imageUrl = url('/') . "/uploads/images" . "/" . $image;
                                        // $imageUrl =
                                        //     "https://ik.imagekit.io/ikmedia/backlit.jpg";
                                        $uploadImage = Http::withHeaders([
                                            "Authorization" =>
                                            "Bearer " . $payload->instragramToken,
                                        ])
                                            ->post(
                                                "https://graph.facebook.com/v16.0/17841458397296954/media?image_url=" .
                                                    $imageUrl .
                                                    "&is_carousel_item=true&caption=" .
                                                    $payload->caption
                                            );
                                        $shareImage[] = $uploadImage->json();
                                    }
                                    // $imageUrl = asset('uploads/images/' . $imageArr['name']);
                                }
                            }
                            $idValues = array_column($shareImage, "id");
                            $idString = implode(",", $idValues);
                            $maxAttempts = 15;
                            $attempt = 1;
                            $timeout = 12;
                            while ($attempt <= $maxAttempts) {
                                try {
                                    $registerUploadmultipleFile = Http::withHeaders([
                                        "Authorization" => "Bearer " . $payload->instragramToken,
                                    ])->post(
                                        "https://graph.facebook.com/v16.0/17841458397296954/media?caption=" .
                                            $payload->caption .
                                            "&media_type=CAROUSEL&children=" .
                                            $idString
                                    );
                                    $registerUploadmultiple = '';
                                    $statusCode = $registerUploadmultipleFile->getStatusCode();
                                    log::info(['registerUploadmultipleFile' => $registerUploadmultipleFile->json()]);
                                    if ($statusCode === 200 || $statusCode === 201) {
                                        $registerUploadmultiple = $registerUploadmultipleFile->json();
                                        log::info($registerUploadmultiple);
                                        // dd($registerUploadmultiple);
                                        // $registerUpload = $registerUploadmultiple;
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
                                    // Handle request exceptions
                                    // You can log or throw an exception here
                                    // For now, we will retry the request
                                    $attempt++;
                                    sleep(5); // Wait for 5 seconds before retrying
                                    return response()->json(
                                        ["message" => $e->getMessage()],
                                        401
                                    );
                                }
                            }
                            while ($attempt <= $maxAttempts) {
                                try {
                                    $registerUploadVideo = Http::withHeaders([
                                        "Authorization" => "Bearer " . $payload->instragramToken,
                                    ])->post(
                                        "https://graph.facebook.com/v16.0/17841458397296954/media_publish?creation_id=" .
                                            $registerUploadmultiple["id"]
                                    );
                                    $statusCode = $registerUploadVideo->getStatusCode();
                                    $registerUpload = $registerUploadVideo->json();
                                    log::info($registerUpload);
                                    if ($statusCode === 200 || $statusCode === 201) {
                                        $registerUpload = $registerUploadVideo->json();
                                        // dd($registerUploadmultiple);
                                        // $registerUpload = $registerUploadmultiple;
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
                                    // Handle request exceptions
                                    // You can log or throw an exception here
                                    // For now, we will retry the request
                                    $attempt++;
                                    sleep(5); // Wait for 5 seconds before retrying
                                    return response()->json(
                                        ["message" => $e->getMessage()],
                                        401
                                    );
                                }
                            }
                            // while ($attempt <= $maxAttempts) {
                            //     try {
                            //         $registerUploadmultipleFile = Http::withHeaders([
                            //             "Authorization" => "Bearer " . $payload->instragramToken,
                            //         ])->post(
                            //             "https://graph.facebook.com/v16.0/17841458397296954/media?caption=" .
                            //             $payload->caption .
                            //             "&media_type=CAROUSEL&children=" .
                            //             $idString
                            //         );

                            //         $registerUploadmultiple = $registerUploadmultipleFile->json();
                            //         $statusCode = $registerUploadmultipleFile->getStatusCode();
                            //         log::info($registerUploadmultiple["id"]);
                            //         if (isset($registerUploadmultiple["id"]) && $registerUploadmultiple["id"] != 0) {
                            //             $registerUpload = Http::withHeaders([
                            //                 "Authorization" => "Bearer " . $payload->instragramToken,
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
                            //     } catch (Exception $e) {
                            //         // Handle request exceptions
                            //         // You can log or throw an exception here
                            //         // For now, we will retry the request
                            //         $attempt++;
                            //         sleep(5); // Wait for 5 seconds before retrying
                            //         return response()->json(
                            //             ["message" => $e->getMessage()],
                            //             401
                            //         );
                            //     }
                            // }
                        }
                    }
                }
                if (in_array("Facebook", (array)$payload->socicalType)) {
                    foreach ($payload->facebookId as $facebookId) {
                        if ($payload->type == "image") {
                            if (count($files) <= 1) {
                                try {
                                    $imageArr["path"] = "uploads/images";
                                    $imagePathUrl = $imageArr["path"] . "/" . $files[0];
                                    $fileGetContent = url('/') . "/uploads/images" . "/" . $files[0];
                                    Log::info($imagePathUrl);
                                    $requestData = [
                                        'message' => !empty($payload->text) ? $payload->text : $payload->text
                                    ];
                                    $imageData = file_get_contents($fileGetContent);
                                    $imageUpload = Http::withHeaders([
                                        "Authorization" =>
                                        "Bearer " . $facebookId->access_token,
                                    ])
                                        ->attach("file", $imageData, $imagePathUrl)
                                        ->post("https://graph.facebook.com/v16.0/" . $facebookId->id . "/photos", $requestData);
                                    Log::info($imageUpload->getStatusCode());
                                    if ($imageUpload->getStatusCode() == 201 || $imageUpload->getStatusCode() == 200) {
                                        $registerUpload = $imageUpload->json();
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
                                foreach ($files as $key => $image) {
                                    try {
                                        $imageArr["path"] = "uploads/images";
                                        $imagePathUrl = $imageArr["path"] . "/" . $image;

                                        $requestData = [
                                            'message' => !empty($payload->text) ? $payload->text : '',
                                            'published' => false
                                        ];

                                        $imageData = file_get_contents($imagePathUrl);
                                        $uploadImageFile = Http::withHeaders([
                                            "Authorization" =>
                                            "Bearer " . $facebookId->access_token,
                                        ])
                                            ->attach("file", $imageData, $imagePathUrl)
                                            ->post("https://graph.facebook.com/v16.0/" . $facebookId->id . "/photos", $requestData);

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
                                foreach ($uploadImage as $item) {
                                    $convertedArray[] = [
                                        "media_fbid" => $item["id"]
                                    ];
                                }
                                try {
                                    $requestData = [
                                        'message' => !empty($payload->text) ? $payload->text : '',
                                        'attached_media' => $convertedArray
                                    ];
                                    $UploadImage = Http::withHeaders([
                                        "Authorization" => "Bearer " . $facebookId->access_token,
                                    ])->post(
                                        "https://graph.facebook.com/v16.0/" . $facebookId->id . "/feed",
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
                        if ($payload->type == "text") {
                            try {
                                $requestparam = [
                                    "message" => !empty($payload->text) ? $payload->text : '',
                                ];
                                $textUpload = Http::withHeaders([
                                    "Authorization" => "Bearer " . $payload->facebookId[0]->access_token,
                                ])
                                    ->post(
                                        "https://graph.facebook.com/v16.0/" . $payload->facebookId[0]->id . "/feed",
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

                        if ($payload->type == "video") {
                            try {
                                $requestparam = [
                                    "description" => !empty($payload->text) ? $payload->text : '',
                                ];
                                $imageArr["path"] = "uploads/images";
                                
                                $imagePathUrl = asset($imageArr["path"] . "/" . $files[0]);
                                // $imagePathUrl = "https://social-media-api.tecmantras.com/uploads/images/20230905061741000507650.mp4";
                            
                                $imageData = file_get_contents($imagePathUrl);
                                $maxAttempts = 5;
                                $attempt = 1;
                                $timeout = 12;
                                while ($attempt <= $maxAttempts) {
                                    try {
                                        $uploadVideoFile = Http::withHeaders([
                                            "Authorization" => "Bearer " . $facebookId->access_token,
                                        ])
                                            ->attach("file", $imageData, $imagePathUrl)
                                            ->post("https://graph.facebook.com/v16.0/" . $facebookId->id . "/videos", $requestparam);
                                        Log::info($uploadVideoFile->getStatusCode());
                                        if ($uploadVideoFile->getStatusCode() == 200) {
                                            $registerUpload = $uploadVideoFile;
                                            break;
                                        } else {
                                            $attempt++;
                                            sleep(7);
                                        }
                                    } catch (Exception $e) {
                                        return response()->json(
                                            ["message" => $e, 'status' => JsonResponse::HTTP_UNAUTHORIZED],
                                            JsonResponse::HTTP_UNAUTHORIZED // Set the desired status code here
                                        );
                                    }
                                }
                            } catch (Exception $e) {
                                return response()->json(
                                    ["message" => $e->getMessage(), 'status' => JsonResponse::HTTP_UNAUTHORIZED],
                                    JsonResponse::HTTP_UNAUTHORIZED // Set the desired status code here
                                );
                            }
                        }
                    }
                }
                if (in_array("Twitter", (array)$payload->socicalType)) {
                    if ($payload->type == "image") {

                        foreach ($files as $key => $image) {
                            try {
                                $imageData = file_get_contents($imagePathUrl);

                                $imageUrl = asset('uploads/images/' . $image);
                                $oauthConsumerKey = "MstSMK2dCJsa78O2Owlb80P3M";
                                $oauthToken = $payload->access_token;
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
                            } catch (Exception $e) {
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
                            $oauthToken =  $payload->access_token;
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
                                "text" => !empty($payload->text) ? $payload->text : '',
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
                    if ($payload->type == "text") {
                        try {
                            $requestparam = [
                                "text" => $payload->text,
                            ];
                            $oauthConsumerKey = "MstSMK2dCJsa78O2Owlb80P3M";
                            $oauthToken = $payload->access_token;
                            $oauthSignatureMethod = "HMAC-SHA1";
                            $oauthTimestamp = "1684996746";
                            $oauthNonce = "dHFHgzAox2a";
                            $oauthVersion = "1.0";
                            $oauthSignature = "e7DE5rdjmZ249X06ZWkkyU8KvOI%3D";
                            $headers = [
                                'Authorization' => 'OAuth oauth_consumer_key="' . $oauthConsumerKey . '",oauth_token="' . $oauthToken . '",oauth_signature_method="' . $oauthSignatureMethod . '",oauth_timestamp="' . $oauthTimestamp . '",oauth_nonce="' . $oauthNonce . '",oauth_version="' . $oauthVersion . '",oauth_signature="' . $oauthSignature . '"',
                                'Content-Type' => 'application/json',
                                'Cookie' => 'guest_id=v1%3A168492609637678520; guest_id_ads=v1%3A168492609637678520; guest_id_marketing=v1%3A168492609637678520; personalization_id="v1_nCLwX8fEC4gb5QdCZFFrWQ=="'
                            ];

                            $textUpload = Http::withHeaders($headers)->post('https://api.twitter.com/2/tweets', $requestparam);

                            if ($textUpload->getStatusCode() == 201 || $textUpload->getStatusCode() == 200) {
                                $registerUpload = $textUpload;
                            } else {
                                return response()->json(
                                    ["message" => $textUpload->json(), 'status' => JsonResponse::HTTP_UNAUTHORIZED],
                                    JsonResponse::HTTP_UNAUTHORIZED // Set the desired status code here
                                );
                            }
                        } catch (Exception $e) {
                            return response()->json(
                                ["message" => $e->getMessage(), 'status' => JsonResponse::HTTP_UNAUTHORIZED],
                                JsonResponse::HTTP_UNAUTHORIZED // Set the desired status code here
                            );
                        }
                    }
                }
                $updatestatus = SocialMediaPayload::where('id', $getpayload['id'])->update(['upload_post_status' => 2]);
            }
        }
        return $registerUpload;
    }

    public function getPageListWithToken()
    {
        return Http::withHeaders([
            "Authorization" => "Bearer " . request()->bearerToken(),
        ])
            ->get("https://graph.facebook.com/1847792015587630/accounts?fields=name,access_token")
            ->json();
    }

    public function getLongLivedAccessToken(Request $request)
    {
        return Http::get("https://graph.facebook.com/oauth/access_token?grant_type=fb_exchange_token&client_id=593353605997257&client_secret=7881f39c324c2fc991d36297dd44e6e1&fb_exchange_token=" . $request['exchange_token'])
            ->json();
    }

    public function requestToken(Request $request)
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Cookie' => 'guest_id=v1%3A168492609637678520; guest_id_ads=v1%3A168492609637678520; guest_id_marketing=v1%3A168492609637678520; personalization_id="v1_nCLwX8fEC4gb5QdCZFFrWQ=="; lang=en'
        ];

        $url = 'https://api.twitter.com/oauth/request_token?oauth_consumer_key=MstSMK2dCJsa78O2Owlb80P3M&oauth_token=' . $request['access_token'] . '&oauth_signature_method=HMAC-SHA1&oauth_timestamp=1685087131&oauth_nonce=TqF1uTqnTwZ&oauth_version=1.0&oauth_signature=5a0ptlw5hfmJeYWdFk0JXy%2BioTU%3D';

        $response = Http::withHeaders($headers)
            ->post($url);
        $responseBody = $response->getBody()->getContents();

        return [
            'token' => $responseBody
        ];
    }

    public function getAccessToken(Request $request)
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Cookie' => 'guest_id=v1%3A168492609637678520; guest_id_ads=v1%3A168492609637678520; guest_id_marketing=v1%3A168492609637678520; personalization_id="v1_nCLwX8fEC4gb5QdCZFFrWQ=="; lang=en'
        ];

        $response = Http::withHeaders($headers)
            ->post('https://api.twitter.com/oauth/access_token?oauth_token=' . $request['oauth_token'] . '&oauth_verifier=' . $request['oauth_verifier']);

        return [
            'token' => $response->body()
        ];
    }

    public function testApi()
    {
        return [
            'token' => "test"
        ];
    }
}
