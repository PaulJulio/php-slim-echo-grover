<?php
namespace PaulJulio\AmazonEchoGrover;

use \Slim\App;
use \PaulJulio\StreamJSON\SlimMiddleware;

class Router {

    public static function Route() {
        $app = new App();
        // Reminder: the request is processed from the bottom up,
        // the response is processed from the top down.

        $app->add(SlimMiddleware::class);
        $app->add(Grover::class);

        $app->run();
    }
}
