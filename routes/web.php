<?php

use Illuminate\Support\Facades\Route;

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

//Main page
Route::view('/', 'Index');
Route::view('/Index', 'Index');
Route::view('/index', 'Index');

//Info page 
Route::view('/Info', 'Info');
Route::view('/info', 'Info');
