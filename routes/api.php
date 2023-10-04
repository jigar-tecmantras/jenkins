<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LinkedInController;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
Route::get('/test-api', [LinkedInController::class, 'testApi']);
Route::post('linkedin-access-token', [LinkedInController::class, 'linkedinGenerateToken']);
Route::get('/get-user-detail', [LinkedInController::class, 'getUserDetail']);
Route::post('register-upload', [LinkedInController::class, 'registerUpload']);
Route::post('upload-image', [LinkedInController::class, 'uploadImage']);
Route::post('image-share', [LinkedInController::class, 'imageShare']);
Route::post('upload-post-on-instragram', [InstragramInController::class, 'imageShare']);
Route::get('/get-instagram-profile', [LinkedInController::class, 'getIntagramProfile']);
Route::get('/get-instagram-profile', [LinkedInController::class, 'getIntagramProfile']);
Route::post('upload_schedule_post',[LinkedInController::class, 'uploadSchedulePost']);
Route::get('/get-page-list-with-token', [LinkedInController::class, 'getPageListWithToken']);
Route::post('/get-long-lived-access-token', [LinkedInController::class, 'getLongLivedAccessToken']);
Route::get('/request-token', [LinkedInController::class, 'requestToken']);
Route::post('/get-access-token', [LinkedInController::class, 'getAccessToken']);
