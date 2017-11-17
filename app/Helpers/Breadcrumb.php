<?php

namespace App\Helpers;


class Breadcrumb
{
    public $link;
    public $name;

    public function __construct($link, $name)
    {
        $this->link = $link;
        $this->name = $name;
    }
}