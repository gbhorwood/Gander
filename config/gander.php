<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Enable gander
    |--------------------------------------------------------------------------
    | Value 'true' is enables gander; 'false' disables. 
    |
    */
    'enabled' => env('GANDER_ENABLE', true),

    /*
    |--------------------------------------------------------------------------
    | Enable gander stack timers
    |--------------------------------------------------------------------------
    | Value 'true' enables 'elapsed_seconds' timers in stack. value 'false' disables.
    | 
    | Stack timers rely on microtime() as hrtime() is unreliable across function
    | calls. however, microtime() may be very slow in virtual environments that do not
    | have vDSO, so the option exists here to turn off stack timing.
    |
    */
    'stack_timers_enabled' => env('GANDER_ENABLE_STACK_TIMERS', true),

    /*
    |--------------------------------------------------------------------------
    | Protect passwords in json
    |--------------------------------------------------------------------------
    | Comma-separated list of keys in json request bodies that contain user 
    | passwords
    |
    */
    'password_keys' => env('GANDER_PASSWORD_KEYS', "password,repeat_password,password_repeat,again_password,password_again"),

    /*
    |--------------------------------------------------------------------------
    | Log request headers
    |--------------------------------------------------------------------------
    | Comma-separated list of request headers to log
    |
    */
    'headers_to_log' => env('GANDER_HEADERS_TO_LOG', "x-authorization,user-agent"),
];
