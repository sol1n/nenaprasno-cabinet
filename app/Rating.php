<?php

namespace App;

use App\Backend;
use App\Traits\Models\AppercodeRequest;

class Rating
{
    use AppercodeRequest;

    public static function get(Backend $backend, String $schema, Array $ids)
    {
        $data = self::jsonRequest([
            'method' => 'POST',
            'json' => $ids,
            'headers' => ['X-Appercode-Session-Token' => $backend->token],
            'url' => $backend->url . 'ratings/' . $schema . '/query'
        ]);

        return collect($data);
    }
}
