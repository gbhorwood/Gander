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

];
