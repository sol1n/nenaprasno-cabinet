<?php

namespace App;

use Illuminate\Support\Collection;

class Language
{
    public static function list(): Collection
    {
        return collect(['Russian' => 'ru', 'English' => 'en', 'Chinese' => 'zh']);
    }
}
