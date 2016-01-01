<?php

require_once('vendor/autoload.php');

$app = new \Slim\App();
// Reminder: the request is processed from the bottom up,
// the response is processed from the top down.

$app->add(\PaulJulio\AmazonEchoGrover\Grover::class);
$app->add(\PaulJulio\StreamJSON\SlimMiddleware::class);

$app->run();