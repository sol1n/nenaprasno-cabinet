<?php

namespace App\Traits\Controllers;

use App\Backend;
use Illuminate\Http\Request;
use App\Exceptions\ActionNotFoundException;

trait ModelActions
{
    public function getListUrl(): String
    {
        return '/' . app(Backend::Class)->code . '/' . $this->baseUrl() . '/';
    }

    public function getSingleUrl(): String
    {
        return '/' . app(Backend::Class)->code . '/' . $this->baseUrl() .'/' . $this->id . '/';
    }

    public function httpResponse($action = null)
    {
        $action = $action ?? request()->input('action');
        return redirect($this->getActionUrl($action));
    }

    public function jsonResponse($action = null)
    {
        $action = $action ?? request()->input('action');
        return response()->json(['status' => 'success', 'action' => 'redirect', 'url' => $this->getActionUrl($action)]);
    }

    private function getActionUrl(String $action): String
    {
        switch ($action) {
            case 'list':
                return $this->getListUrl();
                break;
            case 'form':
                return $this->getSingleUrl();
                break;
            default:
                throw new ActionNotFoundException("Unknown action: " . $action);
                break;
        }
    }
}
