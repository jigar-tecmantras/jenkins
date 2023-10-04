<?php

namespace App\Thread;

use Thread;
use App\Exceptions\LinkedInFailException;
use App\Thread\Thread as ThreadThread;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InstagramThread extends ThreadThread
{
    private $request;
    private $imagePath;

    public function __construct()
    {
        // $this->request = $request;
        // $this->imagePath = $imagePath;
    }

    public function run()
    {
        $this->uploadPostOnInstagram($this->request, $this->imagePath);
    }

    private function uploadPostOnInstagram($request, $imagePath)
    {
        sleep(2);
        $datas = "134";
        return $datas;
        
        if ($request["files"]) {
            if (count($request["files"]) <= 1) {
                dd("this");
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
                    $extension == "mov"
                ) {
                    try {
                        $imageUrl = asset('uploads/images/' . $imagePath[0]);
                        // dd($imageUrl);
                        // $imageUrl =
                        //     "https://social-media-api.tecmantras.com/uploads/images/20230509115334000519093.mp4";
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
                                log::info($shareVideoId);
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
                                        log::info($shareVideo);
                                        $statusCode = $response->getStatusCode();
                                        if ($statusCode === 200 || $statusCode === 201) {
                                            $shareVideo = $response->json();
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
                                            401
                                        );
                                    }
                                }
                            } else {
                                throw new LinkedInFailException("Unauthorized Access!");
                            }
                        } catch (Exception $e) {
                            return response()->json(
                                ["message" => $e->getMessage()],
                                401
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
                        // Handle the exception here
                        // Log the error message or return an error response to the user
                        return response()->json(
                            ["message" => $e->getMessage()],
                            401
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
                            )
                            ->json();
                    } else {
                        throw new LinkedInFailException("Unauthorized Access!");
                    }
                    if (isset($shareImage["id"])) {
                        $registerUpload = Http::withHeaders([
                            "Authorization" =>
                            "Bearer " . $request["instragramToken"],
                        ])
                            ->post(
                                "https://graph.facebook.com/v16.0/17841458397296954/media_publish?creation_id=" .
                                    $shareImage["id"]
                            )
                            ->json();
                    } else {
                        throw new LinkedInFailException();
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
                        $extension == "mov"
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
                            "Authorization" => "Bearer " . $request["instragramToken"],
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
}