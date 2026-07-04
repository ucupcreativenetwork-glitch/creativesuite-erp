<?php

use App\Modules\Integration\Controllers\Api\V1\ApiKeyController;
use App\Modules\Integration\Controllers\Api\V1\AutoReorderController;
use App\Modules\Integration\Controllers\Api\V1\ConnectorController;
use App\Modules\Integration\Controllers\Api\V1\IntegrationLogController;
use App\Modules\Integration\Controllers\Api\V1\WebhookController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api', 'company.context'])->prefix('integrations')->name('integrations.')->group(function (): void {
    Route::get('/meta', [WebhookController::class, 'meta'])->name('meta');

    Route::get('/api-keys', [ApiKeyController::class, 'index'])->name('api-keys.index');
    Route::post('/api-keys', [ApiKeyController::class, 'store'])->name('api-keys.store');
    Route::delete('/api-keys/{publicId}', [ApiKeyController::class, 'destroy'])->name('api-keys.destroy');

    Route::get('/webhooks', [WebhookController::class, 'index'])->name('webhooks.index');
    Route::post('/webhooks', [WebhookController::class, 'store'])->name('webhooks.store');
    Route::patch('/webhooks/{publicId}', [WebhookController::class, 'update'])->name('webhooks.update');
    Route::delete('/webhooks/{publicId}', [WebhookController::class, 'destroy'])->name('webhooks.destroy');
    Route::get('/webhooks/{publicId}/deliveries', [IntegrationLogController::class, 'webhookDeliveries'])->name('webhooks.deliveries');

    Route::get('/auto-reorder', [AutoReorderController::class, 'index'])->name('auto-reorder.index');
    Route::post('/auto-reorder', [AutoReorderController::class, 'store'])->name('auto-reorder.store');
    Route::patch('/auto-reorder/{publicId}', [AutoReorderController::class, 'update'])->name('auto-reorder.update');
    Route::delete('/auto-reorder/{publicId}', [AutoReorderController::class, 'destroy'])->name('auto-reorder.destroy');
    Route::post('/auto-reorder/run', [AutoReorderController::class, 'run'])->name('auto-reorder.run');

    Route::get('/connectors', [ConnectorController::class, 'index'])->name('connectors.index');
    Route::post('/connectors', [ConnectorController::class, 'store'])->name('connectors.store');
    Route::patch('/connectors/{publicId}', [ConnectorController::class, 'update'])->name('connectors.update');
    Route::delete('/connectors/{publicId}', [ConnectorController::class, 'destroy'])->name('connectors.destroy');
    Route::get('/connectors/{publicId}/logs', [IntegrationLogController::class, 'connectorLogs'])->name('connectors.logs');
});