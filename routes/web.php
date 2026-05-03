<?php
use App\Http\Controllers\AdminPanelController;
use Illuminate\Support\Facades\Route;

Route::get('/admin', [AdminPanelController::class, 'index']);