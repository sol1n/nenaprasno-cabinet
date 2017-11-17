<?php
namespace App\Helpers;

/**
 * Class AjaxResponse
 * @package system
 *
 * @property string $type
 * @property string $msg
 * @property array|string $data
 */

class AjaxResponse
{
    const ERROR = 'error';
    const WARNING = 'warning';
    const SUCCESS = 'success';

    const MSG_OPERATION_SUCCESS = 'Success';
    const MSG_OPERATION_ERROR = 'Error';

    public $type;
    public $msg;
    public $data;

    function __construct($type=self::SUCCESS,$msg='Success',$data=[]){
        $this->type = $type;
        $this->msg = $msg;
        $this->data = $data;
    }

    public function setResponseError($msg = '')
    {
        $msg = $msg ? $msg : static::MSG_OPERATION_ERROR;
        $this->type = static::ERROR;
        $this->msg = $msg;
    }

}