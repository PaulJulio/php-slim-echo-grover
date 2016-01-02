<?php
namespace PaulJulio\AmazonEchoGrover;

use \PaulJulio\SlimEcho;
use \PaulJulio\SettingsIni;

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

        $settingsFN = __DIR__ . DIRECTORY_SEPARATOR . 'settings.ini';
        if (!file_exists($settingsFN)) {
            $body->offsetSet('error', 'Missing settings.ini');
            return $response->withBody($body)->withStatus(400);
        }
        $settingsSO = new SettingsIni\SettingsSO();
        $settingsSO->addSettingsFileName($settingsFN);
        $settings = SettingsIni\Settings::Factory($settingsSO);
        $appID = $echoRequest->getApplicationID();
        if (!isset($appID) || $appID !== $settings->id) {
            $body->offsetSet('error', 'Incorrect App ID');
            return $response->withBody($body)->withStatus(400);
        }
        $userID = $echoRequest->getUserID();
        if (!isset($userID)) {
            $body->offsetSet('error', 'No UserID found in request');
            return $response->withBody($body)->withStatus(400);
        }
        $user = $this->getUserRecord($userID);

        if ($echoRequest instanceof SlimEcho\RequestLaunch) {
            $echoResponseSO = $this->processLaunch($echoRequest, $user);
        } elseif ($echoRequest instanceof SlimEcho\RequestSessionEnded) {
            $echoResponseSO = $this->processEnded($echoRequest, $user);
        } elseif ($echoRequest instanceof SlimEcho\RequestIntent) {
            $echoResponseSO = $this->processIntent($echoRequest, $user);
        } else {
            $echoResponseSO = $this->processUnknown($echoRequest, $user);
        }

        $echoResponse = SlimEcho\Response::Factory($echoResponseSO);
        $echoResponse->writeToJsonStream($body);

        return $response->withBody($body)->withStatus(200);
    }

    /**
     * @param string $userID
     * @return array
     */
    private function getUserRecord($userID) {
        $userFN = __DIR__ . DIRECTORY_SEPARATOR . $userID;
        if (file_exists($userFN)) {
            $user = json_decode(file_get_contents($userFN), true);
        } else {
            $user = ['id'=>$userID];
        }
        return $user;
    }

    private function putUserRecord(array $user) {
        $user['last-seen'] = date('r');
        $user['version'] = 1;
        $userFN = __DIR__ . DIRECTORY_SEPARATOR . $user['id'];
        file_put_contents($userFN, json_encode($user));
    }

    /**
     * @param SlimEcho\RequestLaunch $echoRequest
     * @param array $user
     * @return SlimEcho\ResponseSO
     * @throws \Exception
     */
    private function processLaunch(SlimEcho\RequestLaunch $echoRequest, array $user) {
        $speechSO = new SlimEcho\ResponseSpeechSO();
        $speechSO->setType($speechSO::TYPE_PLAIN_TEXT);
        $speechSO->setText('Hello, you can tell me about something you want to keep track of. Right now, I can help you
        keep track of whether the dishes are clean or dirty, and when the dogs were last fed. Try saying "the dishes
        are clean" and then asking about the dishes.');
        $speech = SlimEcho\ResponseSpeech::Factory($speechSO);

        $echoResponseSO = new SlimEcho\ResponseSO();
        $echoResponseSO->endSession();
        $echoResponseSO->setOutputSpeech($speech);

        $this->putUserRecord($user);

        return $echoResponseSO;
    }

    private function processEnded(SlimEcho\RequestSessionEnded $echoRequest, array $user) {
        $speechSO = new SlimEcho\ResponseSpeechSO();
        $speechSO->setType($speechSO::TYPE_PLAIN_TEXT);
        $speechSO->setText('Goodbye');
        $speech = SlimEcho\ResponseSpeech::Factory($speechSO);

        $echoResponseSO = new SlimEcho\ResponseSO();
        $echoResponseSO->endSession();
        $echoResponseSO->setOutputSpeech($speech);

        $this->putUserRecord($user);

        return $echoResponseSO;
    }

    private function processUnknown(SlimEcho\Request $echoRequest, array $user) {
        $speechSO = new SlimEcho\ResponseSpeechSO();
        $speechSO->setType($speechSO::TYPE_PLAIN_TEXT);
        $speechSO->setText('Sorry, I don\'t know how to respond to that type of request');
        $speech = SlimEcho\ResponseSpeech::Factory($speechSO);

        $echoResponseSO = new SlimEcho\ResponseSO();
        $echoResponseSO->endSession();
        $echoResponseSO->setOutputSpeech($speech);

        $this->putUserRecord($user);

        return $echoResponseSO;
    }

    private function processIntent(SlimEcho\RequestIntent $echoRequest, array $user) {
        $speechSO = new SlimEcho\ResponseSpeechSO();

        $slots = $echoRequest->getSlots();
        $noun = $slots['Item']['value'];
        if (isset($slots['Status'])) {
            $status = $slots['Status']['value'];
        } else {
            $status = null;
        }

        if (isset($status)) {
            // updating a status
            $user[$noun] = ['status' => $status, 'since' => date('r')];
            $speechSO->setType($speechSO::TYPE_PLAIN_TEXT);
            $speechSO->setText("I've made a note that the $noun are $status ");
        } else {
            // reporting on a prior status
            if (!isset($user[$noun])) {
                $speechSO->setType($speechSO::TYPE_PLAIN_TEXT);
                $speechSO->setText("Sorry, I don't seem to have a record of your $noun ");
            } else {
                $status = $user[$noun]['status'];
                $speechSO->setType($speechSO::TYPE_PLAIN_TEXT);
                $speechSO->setText("Your $noun are $status ");
            }
        }
        $speech = SlimEcho\ResponseSpeech::Factory($speechSO);

        $echoResponseSO = new SlimEcho\ResponseSO();
        $echoResponseSO->endSession();
        $echoResponseSO->setOutputSpeech($speech);

        $this->putUserRecord($user);

        return $echoResponseSO;
    }
}