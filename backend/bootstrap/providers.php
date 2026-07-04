<?php

use App\Modules\Auth\AuthServiceProvider;
use App\Modules\Business\BusinessServiceProvider;
use App\Modules\Core\CoreServiceProvider;
use App\Modules\Finance\FinanceServiceProvider;
use App\Modules\Iam\IamServiceProvider;
use App\Modules\Integration\IntegrationServiceProvider;
use App\Modules\Platform\PlatformServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    CoreServiceProvider::class,
    AuthServiceProvider::class,
    FinanceServiceProvider::class,
    BusinessServiceProvider::class,
    IamServiceProvider::class,
    IntegrationServiceProvider::class,
    PlatformServiceProvider::class,
];