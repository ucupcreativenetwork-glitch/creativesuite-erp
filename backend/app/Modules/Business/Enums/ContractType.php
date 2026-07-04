<?php

namespace App\Modules\Business\Enums;

enum ContractType: string
{
    case Pkwtt = 'PKWTT';
    case Pkwt = 'PKWT';
    case Intern = 'INTERN';
    case Outsource = 'OUTSOURCE';
}