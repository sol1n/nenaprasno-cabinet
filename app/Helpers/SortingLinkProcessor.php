<?php
/**
 * Created by PhpStorm.
 * User: tsyrya
 * Date: 19/09/2017
 * Time: 16:49
 */

namespace App\Helpers;


class SortingLinkProcessor
{

    CONST DEFAULT_SORTING_ORDER = 'desc';

    public $orderString;
    public $orderFields;
    public $sortQueryParameter;
    public $orderQueryParameter;

    public $defaultSortingField;

    public function __construct($defaultSortingField = 'updatedAt')
    {
        $this->orderString = '';
        $this->orderFields = [];
        $this->sortQueryParameter = '';
        $this->orderQueryParameter = '';
        $this->defaultSortingField = $defaultSortingField;
        $this->parseOrderParamters();
    }

    public function isOrderSet() {
        return $this->orderString ? true : false;
    }

    public function parseOrderParamters()
    {
        $sortFields = [];
        $request = request();
        if ($request->has('_sort')) {
            $sortFields = explode(',', request()->get('_sort'));
        }
        $orders = [];
        if ($request->has('_order')) {
            $orders = explode(',', request()->get('_order'));
        }
        $orderArray = [];
        $orderFields = [];
        foreach ($sortFields as $index => $sortField) {
            $currentFieldOrder = '';
            if (isset($orders[$index]) and mb_strtolower($orders[$index]) == 'desc') {
                $currentFieldOrder = '-';
            }
            $orderArray[] = $currentFieldOrder . $sortField;
            $orderFields[$sortField] = ($currentFieldOrder == '-' ? 'desc' : 'asc');
        }
//        if (empty($orderArray)) {
//            $orderArray[] = $this->defaultSortingField;
//            $orderFields[$this->defaultSortingField] = static::DEFAULT_SORTING_ORDER;
//        }
        $this->orderString = join(',', $orderArray);
        $this->orderFields =$orderFields;
        $this->sortQueryParameter = request()->get('_sort');
        $this->orderQueryParameter = request()->get('_order');
    }

    public function getOrderString()
    {
        $order = static::DEFAULT_SORTING_ORDER == 'asc' ? '' : '-';
        return ($this->orderString ? $this->orderString : $order . $this->defaultSortingField);
    }

    public function getFields() {
        return $this->orderFields;
    }

    public function getFieldOrder($field) {
        return (isset($this->orderFields[$field]) ? $this->orderFields[$field] : '');
    }

    private function generateSortParameter(Array $fields) {
        return $fields ? join(',', array_keys($fields)) : '';
    }

    private function generateOrderParameter(Array $fields) {
        return $fields ? join(',', $fields) : '';
    }
    
    public function generateCurrentSortParameter() {
        return $this->generateSortParameter($this->orderFields);
    }

    public function generateCurrentOrderParameter() {
        return $this->generateOrderParameter($this->orderFields);
    }

    private function switch($value) {
        $result = '';
        if ($value == 'asc') $result = 'desc';
        else $result = 'asc';
        if ($value != static::DEFAULT_SORTING_ORDER) $result = '';
        return $result;
    }

    public function getLinkForField($field)
    {
        $result = '';
        $request = request();
        $queryParameters = $request->query();
        $currentFields = $this->orderFields;
        if (isset($currentFields[$field])) {
            $switchedValue = $this->switch($currentFields[$field]);
            if ($switchedValue) {
                $currentFields[$field] = $switchedValue;
            }
            else{
                unset($currentFields[$field]);
            }
        }
        else{
            $currentFields = [];
            $currentFields[$field] = static::DEFAULT_SORTING_ORDER;
        }
        $_sort = $this->generateSortParameter($currentFields);
        if ($_sort) {
            $queryParameters['_sort'] = $_sort;
        }
        else{
            unset($queryParameters['_sort']);
        }
        $_order = $this->generateOrderParameter($currentFields);
        if ($_order) {
            $queryParameters['_order'] = $this->generateOrderParameter($currentFields);
        }
        else{
            unset($queryParameters['_order']);
        }
        foreach ($queryParameters as $key => $item) {
            if (is_null($item)) {
                $queryParameters[$key] = '';
            }
        }
        $result = http_build_query($queryParameters);
        return $result ? $result : '';
    }

    public function getClassForField($field)
    {
        $result = 'sorting';
        if (isset($this->orderFields[$field])) {
            $result .= '_' . $this->orderFields[$field];
        }
        return $result;
    }
}