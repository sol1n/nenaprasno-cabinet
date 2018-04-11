<?php
/**
 * Created by PhpStorm.
 * User: tsyrya
 * Date: 14/12/2017
 * Time: 13:56
 */

namespace App\Traits\Models;


trait ViewData
{
    /**
     * Get an item from view data array using "dot" notation.
     * @param string $key
     * @return mixed
     */
    public function viewDataAttr(string $key)
    {
        $keys = explode('.', $key);
        $array = $this->viewData;
        foreach ($keys as $segment) {
            if (is_array($array) && array_key_exists($segment, $array)) {
                $array = $array[$segment];
            }
            else {
                return null;
            }
        }
        return $array;
    }
}