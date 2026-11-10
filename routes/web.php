<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Web routes for the application
|
*/

Route::get('/', function () {
    return view('welcome');
});

// Add any other web routes you need here
Route::get('/home', function () {
    return view('home');
})->name('home');


