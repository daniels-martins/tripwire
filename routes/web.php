<?php

use Illuminate\Support\Facades\Route;

Route::livewire('/', 'pages::scoreboard')->name('home');
Route::livewire('/spread', 'pages::spread-timeline')->name('spread');
Route::livewire('/size-curve', 'pages::size-curve')->name('size-curve');
Route::livewire('/corridors', 'pages::corridors')->name('corridors');
Route::livewire('/reliability', 'pages::reliability')->name('reliability');
Route::livewire('/settlements', 'pages::settlements')->name('settlements');
Route::view('dashboard', 'dashboard')->name('dashboard');
