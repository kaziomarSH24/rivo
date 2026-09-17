<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Care Task XP Rewards
    |--------------------------------------------------------------------------
    |
    | Here you can configure the amount of XP earned for different types of
    | care tasks. If a task type is not listed here, it will fallback to
    | the 'default' value.
    |
    */
    'xp_rewards' => [
        'walk'     => 20,
        'medicine' => 15,
        'food'     => 10,
        'sleep'    => 10,
        'water'    => 5,
        
        'default'  => 10,
    ],
];
