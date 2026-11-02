<?php

use App\Http\Controllers\ImageController;
use App\Http\Controllers\PlaceController;
use App\Http\Controllers\WeatherController;
use Illuminate\Support\Facades\Route;

Route::get('/places', PlaceController::class);
Route::get('/weather', WeatherController::class);
// every image costs money on the provider's side
Route::post('/image', ImageController::class)->middleware('throttle:6,1');
