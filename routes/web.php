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
});

//Redirects to voyager admin panel
Route::redirect('/', '/admin');
Route::redirect('/Index', '/admin');
Route::redirect('/index', '/admin');

//Info page 
Route::view('/Info', 'Info');
Route::view('/info', 'Info');

Route::get('processVideo', [VideoController::class, 'processVideo']);
