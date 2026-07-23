<?php

use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['status' => 'ok', 'application' => config('app.name')]));
Route::get('/jobs', fn () => response()->json(['data' => [], 'message' => 'Jobs will be available after the Jobs module is built.']));
