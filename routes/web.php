<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    // DB::connection()->getPdo();

    return view('welcome');
});
