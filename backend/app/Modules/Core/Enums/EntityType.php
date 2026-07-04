<?php

namespace App\Modules\Core\Enums;

enum EntityType: string
{
    case Pt = 'PT';
    case Cv = 'CV';
    case Ud = 'UD';
    case Koperasi = 'KOPERASI';
    case Perorangan = 'PERORANGAN';
}