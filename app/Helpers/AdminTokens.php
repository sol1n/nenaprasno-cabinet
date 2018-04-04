<?php
/**
 * Created by PhpStorm.
 * User: tsyrya
 * Date: 11/01/2018
 * Time: 15:11
 */

namespace App\Helpers;


use App\Backend;
use App\Exceptions\Common\InvalidTokenException;
use App\Exceptions\Common\InvalidTokenFileException;
use App\Exceptions\Common\TokenFileNotExistException;
use App\Mail\TokenAlert;
use App\Traits\Models\AppercodeRequest;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

class AdminTokens
{
    use AppercodeRequest;

    /**
     * @var Collection
     */
    private $tokensList;

    /**
     * Reads file with tokens, path to file with tokens is stored in ENV file
     * Json file structure:
     * {
     *  <backendCode>: {
     *          "refresh-token": <refreshToken>,
     *          "server": <server url>
     * }
     *  <backendCode1>: {
     *          "refresh-token": <refreshToken1>,
     *          "server": <server url1>
     * }
     *  ...
     * }
     * @return \Illuminate\Support\Collection
     * @throws \Exception
     */
    public function getList()
    {
        $path = env('TOKENS_JSON');
        if (file_exists($path)) {
            $content = file_get_contents($path);
            try {
                $list = json_decode($content, 1);
                $this->tokensList = $list;
                return collect($list);
            }
            catch (\Exception $e) {
                throw new InvalidTokenFileException('Json is not valid');
            }
        }
        else{
            throw new TokenFileNotExistException('Directory ' . $path . ' doesn\'t exist');
        }
    }

    /**
     * Generates new sessionId based on the refresh token from json file (readFile())
     * @param $backendCode
     * @param $server
     * @return Backend|null
     * @throws \Exception
     */
    public function getSession($backendCode, $server = '')
    {
        $session = null;
        $receiver = ENV('NOTIFICATION_RECEIVER');
        if (!$this->tokensList) {
            try {
                $list = $this->getList();
            }
            catch (\Exception $e) {
                if ($receiver) {
                    if ($e instanceof InvalidTokenFileException) {
                        Mail::to($receiver)->send(new TokenAlert($backendCode, 'invalid_file'));
                    }
                    if ($e instanceof TokenFileNotExistException) {
                        Mail::to($receiver)->send(new TokenAlert($backendCode, 'nofile'));
                    }
                }
            }
        }
        else{
            $list = $this->tokensList;
        }
        if (isset($list[$backendCode]) and $list[$backendCode]) {
            $refreshToken = $list[$backendCode]['refresh-token'] ?? '';
            if (!$server) {
                $server = $list[$backendCode]['server'] ?? '';
            }
            $backend = new Backend($backendCode, $server);
            try {
                $json = self::jsonRequest([
                    'method' => 'POST',
                    'headers' => ['Content-Type' => 'application/json'],
                    'body' => '"' . $refreshToken . '"',
                    'url' => $backend->url . 'login/byToken'
                ], false);

                $backend->token = $json['sessionId'];
                $session = $backend;
            }
            catch (ClientException $e) {
                $this->invalidException($receiver, $backendCode);
            }
        }
        if (is_null($session)) {
            $this->invalidException($receiver, $backendCode);
        }
        return $session;
    }

    private function invalidException($receiver, $backendCode) {
        if ($receiver) {
            Mail::to($receiver)->send(new TokenAlert($backendCode, 'invalid_token'));
        }
        throw new InvalidTokenException($backendCode . ' is not provided in sessions list');
    }
}