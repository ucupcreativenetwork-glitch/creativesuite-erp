<?php

namespace App\Modules\Integration\Enums;

enum WebhookEvent: string
{
    case AttendanceRecorded = 'attendance.recorded';
    case AttendanceImported = 'attendance.imported';
    case PurchasingOrderCreated = 'purchasing.order.created';
    case PurchasingOrderReceived = 'purchasing.order.received';
    case InventoryLowStock = 'inventory.low_stock';
    case ConnectorReceived = 'connector.received';
}