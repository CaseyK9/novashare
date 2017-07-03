<?php
// Application middleware

$app->add(new \Zeuxisoo\Whoops\Provider\Slim\WhoopsMiddleware);

$app->add(new \Slim\Middleware\Session([
    'name' => 'novashare',
    'autorefresh' => true,
    'lifetime' => '1 day'
]));
