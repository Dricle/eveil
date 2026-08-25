<?php

use App\Cloud\CloudServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\HorizonServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    HorizonServiceProvider::class,
    // Self-gates on `eveil.edition`: see the class docblock. Last, so its
    // SpendGuardInterface binding overrides AppServiceProvider's default.
    CloudServiceProvider::class,
];
