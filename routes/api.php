<?php

use App\Http\Controllers\PlaceController;
use App\Http\Controllers\WeatherController;
use Illuminate\Support\Facades\Route;

Route::get('/places', PlaceController::class);
Route::get('/weather', WeatherController::class);
