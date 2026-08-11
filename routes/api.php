<?php

use App\Http\Controllers\Api\ClickupWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('webhooks/clickup/{workspace:id}', ClickupWebhookController::class);
