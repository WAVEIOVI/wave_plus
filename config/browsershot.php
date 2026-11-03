<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Browsershot Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Browsershot PDF generation.
    |
    */

    'chrome_path' => env('BROWSERSHOT_CHROME_PATH', 'C:\Program Files\Google\Chrome\Application\chrome.exe'),

    'node_path' => env('BROWSERSHOT_NODE_PATH'),

    'npm_path' => env('BROWSERSHOT_NPM_PATH'),

];