<?php

namespace App;

use App\Backend;
use Carbon\Carbon;
use App\Traits\Models\AppercodeRequest;

class TestResult
{
    use AppercodeRequest;
    
    const REQUEST_PATH = 'testResults/byUser/';

    public $raw;

    public static function get($userId)
    {
        $backend = app(Backend::class);

        $result = new self;
        $result->raw = self::jsonRequest([
            'method' => 'GET',
            'headers' => ['X-Appercode-Session-Token' => $backend->token],
            'url' => $backend->url . self::REQUEST_PATH . $userId . '?recommendation=true',
        ]);

        return $result;
    }

    public static function getUserData($userId)
    {
        $backend = app(Backend::class);

        $result = new self;
        $result->raw = self::jsonRequest([
            'method' => 'GET',
            'headers' => ['X-Appercode-Session-Token' => $backend->token],
            'url' => $backend->url . 'forms/1/response',
        ]);

        return $result;
    }

    private function getClinicsForProcedure($clinics, $procedureId)
    {
        $clinicsSet = [];
        foreach ($clinics as $clinic) {
            if (isset($clinic['medicalProcedures']) && in_array($procedureId, $clinic['medicalProcedures'])) {
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
            if (array_get($raw, 'TestResult.formResponceId') > $maxId) {
                $maxId = array_get($raw, 'TestResult.formResponceId');
            }
        }
        return array_where($this->raw, function ($value, $key) use ($maxId) {
           return array_get($value, 'TestResult.formResponceId') == $maxId;
        });
    }
}
