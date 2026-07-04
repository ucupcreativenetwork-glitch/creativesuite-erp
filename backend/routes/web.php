<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $host = request()->getSchemeAndHttpHost();

    return view('server-info', [
        'apiBase' => $host,
        'webUrl' => preg_replace(':8000$:', '3000', $host),
    ]);
});