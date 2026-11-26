<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

foreach (glob(__DIR__.'/modules/*.php') as $moduleRoutes) {
    require $moduleRoutes;
}
