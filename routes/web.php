<?php

/*
 * Authentication only. Every dashboard, website and API route is registered
 * by its module's service provider (app/Modules/<Module>/routes).
 */

use Illuminate\Support\Facades\Auth;

Auth::routes(['register' => false, 'verify' => false]);
