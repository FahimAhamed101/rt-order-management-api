<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Define Route Middleware Aliases here
        $middleware->alias([
            'jwt.verify' => \App\Http\Middleware\JwtMiddleware::class,
            'jwt.auth' => \Tymon\JWTAuth\Http\Middleware\GetUserFromToken::class,
            'jwt.refresh' => \Tymon\JWTAuth\Http\Middleware\RefreshToken::class,
        ]);
        
        // If you were defining global middleware (applied to all requests), 
        // you would use $middleware->web() or $middleware->api() here.

    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();