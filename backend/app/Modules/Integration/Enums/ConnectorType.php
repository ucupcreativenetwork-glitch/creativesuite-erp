<?php

namespace App\Modules\Integration\Enums;

enum ConnectorType: string
{
    case Zkteco = 'zkteco';
    case Hikvision = 'hikvision';
    case Custom = 'custom';
}