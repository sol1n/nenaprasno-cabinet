<?php

namespace App\Http\Controllers;

use App;
use App\User;
use App\Schema;
use App\Backend;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Services\ObjectManager;
use App\Services\SchemaManager;
use App\Traits\Models\AppercodeRequest;

class UserController extends Controller
{
    use AppercodeRequest;

    private function subscribeProfile(int $userId, $subscribes)
    {
        $profiles = self::jsonRequest([
            'url' => app(Backend::Class)->url . "/users/$userId/profiles",
            'method' => 'GET',
            'headers' => [
                'X-Appercode-Session-Token' => app(Backend::Class)->token
            ]
        ]);
        if (is_array($profiles) && count($profiles) && isset($profiles[0])) {
            $schema = app(SchemaManager::Class)->find($profiles[0]['schemaId']);
            if ($subscribes) {
                app(ObjectManager::Class)->save($schema, $profiles[0]['itemId'], $subscribes);
            }
            return app(ObjectManager::Class)->find($schema, $profiles[0]['itemId']);
        } else {
            return null;
        }
    }

    public function subscribe(Request $request)
    {
        if ($request->has('refreshToken')) {
           $user = new User;
            $backend = app(Backend::Class);
            $user->setRefreshToken($request->get('refreshToken'));
            $user->regenerate($backend);

            $backend->token = $user->token();
            App::instance(Backend::class, $backend);

            $subscribes = [];
            if ($request->has('nenaprasno')) {
                $subscribes['getEmails'] = $request->get('nenaprasno') == 1 ? true : false;
            }
            if ($request->has('media')) {
                $subscribes['getMediaEmails'] = $request->get('media') == 1 ? true : false;
            }

            $userProfile = $this->subscribeProfile((int) $user->id, $subscribes);


            return response()->json([
                'nenaprasno' => $userProfile->fields['getEmails'],
                'media' => $userProfile->fields['getMediaEmails']
            ]); 
        } else {
            return response()->json(['error' => '401']);
        }
        
    }
}
