<?php

use App\Http\Controllers\PublicTreePageController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/'.trim((string) config('durian.public_tree.path_prefix', 't'), '/').'/{token}', [PublicTreePageController::class, 'show'])
    ->middleware('throttle:public-tree')
    ->name('public.trees.show');
