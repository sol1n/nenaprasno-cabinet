<?php

namespace App\Traits\Models;

use App\Schema;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cookie;

trait FieldsFormats
{
    private static function formatStringField($data, $field)
    {
        if ($field['multiple']) {
            if ($data) {
                foreach ($data as $k => $v) {
                    if ($v) {
                        $data[$k] = (String)$v;
                        $data[$k] = $data[$k] == '<p><br></p>' ? '' : $data[$k];
                    } else {
                        unset($data[$k]);
                    }
                }
                if (empty($data)) {
                    $data = null;
                }
            } else {
                $data = null;
            }
        } else {
            if ($data) {
                $data = (String)$data;
                $data = $data == '<p><br></p>' ? '' : $data;
            } else {
                $data = null;
            }
        }

        return $data;
    }

    private static function formatJsonField($data, $field)
    {
        if ($field['multiple']) {
            if ($data) {
                foreach ($data as $k => $v) {
                    if (!$v) {
                        unset($data[$k]);
                    } else {
                        if (is_array($v)) {
                            $data[$k] = json_encode($v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                        }
                    }
                }
                if (empty($data)) {
                    $data = null;
                }
            } else {
                $data = null;
            }
        } else {
            if (!$data) {
                $data = null;
            } else {
                if (is_array($data)) {
                    $data = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                }
            }
        }

        return $data;
    }

    private static function formatIntegerField($data, $field)
    {
        if ($field['multiple']) {
            if ($data) {
                foreach ($data as $k => $v) {
                    if ($v) {
                        $data[$k] = (Integer)$v;
                    } else {
                        unset($data[$k]);
                    }
                }
                if (empty($data)) {
                    $data = null;
                }
            } else {
                $data = null;
            }
        } else {
            if (is_numeric($data)) {
                $data = (Integer)$data;
            } else {
                $data = null;
            }
        }

        return $data;
    }

    private static function formatFloatField($data, $field)
    {
        if ($field['multiple']) {
            if ($data) {
                foreach ($data as $k => $v) {
                    if ($v) {
                        $data[$k] = (Float)$v;
                    } else {
                        unset($data[$k]);
                    }
                }
                if (empty($data)) {
                    $data = null;
                }
            } else {
                $data = null;
            }
        } else {
            if ($data) {
                $data = (Float)$data;
            } else {
                $data = null;
            }
        }
        return $data;
    }

    private static function formatDateTimeField($data, $field, Schema $schema)
    {
        $timezone = $schema->getTimezoneForField($field['name']);
        $convert = function ($data) use($timezone) {
            $date = new Carbon($data, $timezone);
            if ($timezone != 'UTC') {
                $date->setTimezone('UTC');
            }
            return $date->toAtomString();
        };
        if ($field['multiple']) {
            if ($data) {
                foreach ($data as $k => $v) {
                    if ($v) {
                        $data[$k] = $convert($v);
                    } else {
                        unset($data[$k]);
                    }
                }
                if (empty($data)) {
                    $data = null;
                }
            } else {
                $data = null;
            }
        } else {
            if ($data) {
//                $date = new Carbon($data, $timezone);
                $data = $convert($data);// $date->toAtomString();
            } else {
                $data = null;
            }
        }
        return $data;
    }

    private static function formatBooleanField($data, $field)
    {
        if ($field['multiple']) {
            if ($data) {
                foreach ($data as $k => $v) {
                    if ($v) {
                        $data[$k] = is_bool($data[$k]) ? $data[$k] : $data[$k] == 'on';
                    } else {
                        unset($data[$k]);
                    }
                }
                if (empty($data)) {
                    $data = null;
                }
            } else {
                $data = null;
            }
        } else {
            if (is_bool($data) || is_string($data)) {
                $data = is_bool($data) ? $data : $data == 'on';
            } else {
                $data = null;
            }
        }

        return $data;
    }

    private static function prepareRawData($data, Schema $schema, $keepNull = true): array
    {
        $systemFields = ['id', 'createdAt', 'ownerId'];
        foreach ($systemFields as $field) {
            if (array_key_exists($field, $data)) {
                unset($data[$field]);
            }
        }

        foreach ($schema->fields as $field) {
            if (isset($data[$field['name']])) {
                switch ($field['type']) {
                    case 'String':
                    case 'Uuid':
                    case 'Text':
                        $data[$field['name']] = self::formatStringField($data[$field['name']], $field);
                        break;
                    case 'Json':
                        $data[$field['name']] = self::formatJsonField($data[$field['name']], $field);
                        break;
                    case 'DateTime':
                        $data[$field['name']] = self::formatDateTimeField($data[$field['name']], $field, $schema);
                        break;
                    case 'Integer':
                        $data[$field['name']] = self::formatIntegerField($data[$field['name']], $field);
                        break;
                    case 'Boolean':
                            $data[$field['name']] = self::formatBooleanField($data[$field['name']], $field);
                        break;
                    case 'Double':
                    case 'Money':
                      $data[$field['name']] = self::formatFloatField($data[$field['name']], $field);
                        break;
                    default:
                        break;
                }
                if ($field['multiple'] and !is_null($data[$field['name']]) and !is_array($data[$field['name']])) {
                    $data[$field['name']] = [$data[$field['name']]];
                }
            }
            else {
                switch ($field['type']) {
                    case 'Boolean':
                        $data[$field['name']] = false;
                    break;
                }
            }

        }

        if (!$keepNull) {
            foreach ($data as $key => $value) {
                if (is_null($value)) {
                    unset($data[$key]);
                }
            }
        }

        return $data;
    }
}
