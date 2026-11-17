<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Only browser-facing routes live here. The product dashboard is a React
| application mounted by resources/views/dashboard.blade.php; all machine
| endpoints live in routes/api.php under the /eurocross prefix.
|
*/

Route::view('/', 'dashboard')->name('dashboard');
