<?php

use Illuminate\Support\Facades\Route;

Route::livewire('/', 'pages::scoreboard')->name('home');
Route::view('dashboard', 'dashboard')->name('dashboard');
