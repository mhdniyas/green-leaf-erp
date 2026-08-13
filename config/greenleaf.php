<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Green Leaf Company Identity
    |--------------------------------------------------------------------------
    | Used by the Cashbook dashboard and ledger engine to display company
    | branding without hardcoding strings in views or controllers.
    */
    'name'         => env('GREENLEAF_NAME', 'Green Leaf'),
    'display_name' => env('GREENLEAF_DISPLAY_NAME', 'Green Leaf Traders'),
    'logo_url'     => env('GREENLEAF_LOGO_URL', null),
];
