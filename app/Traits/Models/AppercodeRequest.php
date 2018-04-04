<?php

namespace App\Traits\Models;

use App\User;
use App\Backend;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use GuzzleHttp\Exception\ClientException;

trait AppercodeRequest
{
    /**
     * Returns optional parameters separately
     * @param  array  $data All parameters
     * @return array of [method, url, others]
     */
    private static function parseRequestData(array $data): array
    {
        $params = $data;
        unset($params['url']);
        unset($params['method']);
        return [
            $data['method'] ?? null,
            $data['url'] ?? null,
            $params
        ];
    }

    /**
     * Makes a request to appercode server and handle non-authorized answers
     * @param  array  $data
     * @param  bool  $catch if true - need to catch 401 exception
     * @return GuzzleHttp\Psr7\Response
     */
    protected static function request($data, $catch = true): GuzzleResponse
    {
        $client = new Client;
        list($method, $url, $params) = self::parseRequestData($data);
        try {
            return $client->request($method, $url, $params);
        } catch (ClientException $e) {
            if (self::checkTokenExpiration($e) && $catch) {
                return self::recallMethod($data);
            }
            throw $e;
        }
    }

    /**
     * Returns json from response body
     * @param  array  $data
     * @param  bool  $catch if true - need to catch 401 exception
     * @return array parsed json
     */
    protected static function jsonRequest(array $data, bool $catch = true): array
    {
        $result = self::request($data, $catch);
        if ($result instanceof GuzzleResponse) {
            if ($result->getStatusCode() == 204){
                return [];
            }
            return json_decode($result->getBody()->getContents(), 1);
        }
        return $result;
    }

    /**
     * Returns count from response headers
     * @param  array  $data
     * @param  bool  $catch
     * @return int count of elements
     */
    protected static function countRequest(array $data, bool $catch = true): int
    {
        $result = self::request($data, $catch);
        if ($result instanceof GuzzleResponse) {
            return (int) $result->getHeader('x-appercode-totalitems')[0];
        }
        return $result;
    }

    /**
     * Gets new appercode token
     * @return Backend with new token
     */
    private static function regenerateToken(): Backend
    {
        $user = new User();
        $user->regenerate(app(Backend::class));

        $backend = app(Backend::class);
        $backend->token = $user->token();

        app()->instance(Backend::class, $backend);

        return $backend;
    }

    /**
     * Checks that exception can be handled by regeneration appercode token
     * @param  GuzzleHttp\Exception\ClientException $e
     * @return bool true, if exception have unauthorized response code
     */
    private static function checkTokenExpiration(ClientException $e): bool
    {
        return ($e->hasResponse() && $e->getResponse()->getStatusCode() == 401);
    }

    /**
     * Makes new request after getting new appercode token
     * @return GuzzleHttp\Psr7\Response
     */
    private static function recallMethod(array $data): GuzzleResponse
    {
        $backend = self::regenerateToken();
        if (isset($data['headers']['X-Appercode-Session-Token'])) {
            $data['headers']['X-Appercode-Session-Token'] = $backend->token;
        }
        return self::request($data);
    }
}
