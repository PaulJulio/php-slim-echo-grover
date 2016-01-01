<?php
namespace PaulJulio\AmazonEchoGrover;

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

        $requestSO = new \PaulJulio\SlimEcho\RequestSO();
        $requestSO->setHttpRequest($request->getBody());
        $echoRequest = \PaulJulio\SlimEcho\Request::Factory($requestSO);
        // make decisions based on the request

        $speechSO = new \PaulJulio\SlimEcho\ResponseSpeechSO();
        $speechSO->setType($speechSO::TYPE_PLAIN_TEXT);
        $speechSO->setText('This is example text. I am a very good robot. Trust me.');

        $echoResponseSO = new \PaulJulio\SlimEcho\ResponseSO();
        $echoResponseSO->endSession();
        $echoResponseSO->setOutputSpeech($speechSO);

        $echoResponse = \PaulJulio\SlimEcho\Response::Factory($echoResponseSO);
        $echoResponse->writeToJsonStream($body);

        return $response->withBody($body);
    }

}