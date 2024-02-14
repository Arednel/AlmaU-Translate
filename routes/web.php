<?php

use Illuminate\Support\Facades\Route;

use TCG\Voyager\Facades\Voyager;

use App\Http\Controllers\VideoController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

//Voyager admin panel
Route::group(['prefix' => 'admin'], function () {
    Voyager::routes();

    // Custom video store logic
    Route::post('/videos', [VideoController::class, 'store'])
        ->name('voyager.videos.store')
        ->middleware('auth');

    // Custom video delete logic (for one or multiple videos)
    Route::delete('/videos/{id}', [VideoController::class, 'destroy'])
        ->whereNumber('id')
        ->name('voyager.videos.destroy')
        ->middleware('auth');

    Route::get('/videos/translated_view/{id}', [VideoController::class, 'translatedView'])
        ->whereNumber('id')
        ->name('translated_view')
        ->middleware('auth');
});

//Redirects to voyager admin panel
Route::redirect('/', '/admin');
Route::redirect('/Index', '/admin');
Route::redirect('/index', '/admin');

//Info page 
Route::view('/Info', 'Info');
Route::view('/info', 'Info');
