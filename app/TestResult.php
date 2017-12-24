<?php

namespace App;

use App\Form;
use App\Backend;
use Carbon\Carbon;
use GuzzleHttp\Psr7;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use App\Exceptions\Backend\TokenExpiredException;

class TestResult
{
    const REQUEST_PATH = 'testResults/byUser/';

    public $raw;

    public static function get($userId)
    {
        $backend = app(Backend::class);
        $client = new Client();
        try {
            $r = $client->get($backend->url . self::REQUEST_PATH . $userId . '?recommendation=true', ['headers' => [
              'X-Appercode-Session-Token' => $backend->token
          ]]);
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                if ($e->getResponse()->getStatusCode() == 401) {
                    throw new TokenExpiredException;
                }
            }
            throw new \Exception('Error while getting test results');
        };

        $result = new self;
        $result->raw = json_decode($r->getBody()->getContents());

        return $result;
    }

    public static function getUserData($userId)
    {
        $backend = app(Backend::class);
        $client = new Client();

        try {
            $r = $client->get($backend->url . 'forms/1/response', ['headers' => [
              'X-Appercode-Session-Token' => $backend->token
          ]]);
        } catch (RequestException $e) {
            throw new \Exception('Error while getting user data');
        };

        $result = new self;
        $result->raw = json_decode($r->getBody()->getContents());

        return $result;
    }

    private function getClinicsForProcedure($clinics, $procedureId)
    {
        $clinicsSet = [];
        foreach ($clinics as $clinic) {
            if (in_array($procedureId, $clinic['medicalProcedures'])) {
                $clinicsSet[] = $clinic;
            }
        }
        return $clinicsSet;
    }

    public function getProcedures($medicalProcedures, $clinics, $userProcedures)
    {
        $data = [];

        if (isset($this->raw)) {
            foreach ($this->lastResults() as $result) {
                if (isset($result->Recommendations) && $result->Recommendations) {

                    $created = new Carbon($result->TestResult->createdAt);

                    foreach ($result->Recommendations as $recommendation) {
                        $procedure = $medicalProcedures[$recommendation->medicalProcedureId];

                        $procedureDate = isset($userProcedures[$procedure['id']]) ? new Carbon($userProcedures[$procedure['id']]) : null;

                        $data[$procedure['id']] = [
                            'id' => $recommendation->medicalProcedureId,
                            'name' => $procedure['name'] ?? null,
                            'description' => $procedure['description'] ?? null,
                            'repeatCount' => $recommendation->repeatCount,
                            'clinics' => $this->getClinicsForProcedure($clinics, $procedure['id']),
                            'firstShown' => is_null($procedureDate),
                            'nextDate' => isset($procedure['periodicity']) && $procedure['periodicity'] && $procedureDate ? $procedureDate->addDays($procedure['periodicity']) : null,
                            'date' => $created
                          ];
                    }
                }
            }
            return collect($data)->all();
        } else {
            return null;
        }
    }

    public function lastResults()
    {
        $maxId = 0;
        foreach ($this->raw as $raw) {
            if ($raw->TestResult->formResponceId > $maxId) {
                $maxId = $raw->TestResult->formResponceId;
            }
        }
        return array_where($this->raw, function ($value, $key) use ($maxId) {
           return $value->TestResult->formResponceId == $maxId;
        });
    }
}
