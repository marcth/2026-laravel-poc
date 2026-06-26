<?php

declare(strict_types=1);

use App\Hello\Actions\SayHello;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/hello', SayHello::class);
});
