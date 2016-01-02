<?php
namespace PaulJulio\AmazonEchoGrover;

use \PaulJulio\SlimEcho;

class Grover {
    /**
     * @param  \Psr\Http\Message\ServerRequestInterface $request  PSR7 request
     * @param  \Psr\Http\Message\ResponseInterface      $response PSR7 response
     * @param  callable                                 $next     Next middleware
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function __invoke($request, $response, $next) {
        $response = $next($request, $response);
        $body = $response->getBody();

        $requestSO = new SlimEcho\RequestSO();
        $requestSO->setHttpRequest($request->getBody());

        $echoRequest = SlimEcho\Request::Factory($requestSO);
        $userID = $echoRequest->getUserID();
        if (!isset($userID)) {
            $body->offsetSet('error', 'No UserID found in request');
            return $response->withBody($body)->withStatus(400);
        }
        $userFN = __DIR__ . DIRECTORY_SEPARATOR . $userID;

        $speechSO = new SlimEcho\ResponseSpeechSO();
        $speechSO->setType($speechSO::TYPE_PLAIN_TEXT);
        $speechSO->setText('This is example text. I am a very good robot. Trust me.');
        $speech = SlimEcho\ResponseSpeech::Factory($speechSO);

        $echoResponseSO = new SlimEcho\ResponseSO();
        $echoResponseSO->endSession();
        $echoResponseSO->setOutputSpeech($speech);

        $echoResponse = SlimEcho\Response::Factory($echoResponseSO);
        $echoResponse->writeToJsonStream($body);

        return $response->withBody($body)->withStatus(200);
    }

}