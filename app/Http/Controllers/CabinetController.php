<?php

namespace App\Http\Controllers;

use Cookie;
use App\User;
use App\Form;
use App\Backend;
use Carbon\Carbon;
use App\TestResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\MessageBag;
use App\Services\UserManager;
use App\Services\ObjectManager;
use App\Services\SchemaManager;
use App\Exceptions\User\WrongCredentialsException;
use App\Traits\Models\AppercodeRequest;
use GuzzleHttp\Exception\ClientException;

class CabinetController extends Controller
{
    use AppercodeRequest;

    const FORM_ID = 1;
    const PROFILE_SCHEMA_NAME = 'UserProfiles';

    private function getDiseases($schemaManager, $objectManager)
    {
        return $objectManager
            ->search($schemaManager->find('Disease'), ['take' => -1])
            ->mapWithKeys(function ($item) {
                return [$item->id => $item->fields];
            });
    }

    private function getMedicalProcedures($schemaManager, $objectManager)
    {
        return $objectManager
            ->search($schemaManager->find('MedicalProcedure'), ['take' => -1])
            ->mapWithKeys(function ($item) {
                return [$item->id => array_merge($item->fields, ['id' => $item->id])];
            });
    }

    private function getClinicsProcedures($schemaManager, $objectManager)
    {
        return $objectManager
            ->search($schemaManager->find('MedicalProcedure'), ['take' => -1])
            ->mapWithKeys(function ($item) {
                return [$item->id => $item->fields];
            });
    }

    private function getClinicsForRegion($region, $schemaManager, $objectManager)
    {
        $query = json_encode(['$or' => [['regionId' => $region], ['allCities' => true]]]);
        return $objectManager
            ->search($schemaManager->find('Clinic'), ['where' => $query, 'take' => -1])
            ->mapWithKeys(function ($item) {
                return [$item->id => $item->fields];
            });
    }

    private function extractProfileData($response)
    {
        $birthday = new Carbon();
        $age = isset($response['t1-p3-s2-g1-c1']) ? $response['t1-p3-s2-g1-c1'] : 0;
        $birthday->subYears($age)->startOfYear();
        return [
            'regionId' => isset($response['reg1']['value']) ? (integer) $response['reg1']['value'] : null,
            'sex' => isset($response['t1-p1-s1-g1-c1']['value']) ? (integer) $response['t1-p1-s1-g1-c1']['value'] : null,
            'birthdate' => $birthday->format('d.m.Y')
        ];
    }

    private function createProfile($schemaManager, $objectManager, $userId)
    {
        $userResponse = Form::getOwnResponses(app(Backend::class), self::FORM_ID);
        if (! is_null($userResponse)) {
            $userResponse = $userResponse->mapWithKeys(function($item) {
                $index = isset($item['value']['value']) ? $item['value']['value'] : null;

                if ($item['controlType'] == 'numberInput') {
                    return [$item['controlId'] => $index];
                } else {
                    $value = null;
                    if (is_array($item['options']['value'])) {
                        foreach ($item['options']['value'] as $one) {
                            if ($index == (integer) $one['value']) {
                                $value = $one;
                            }
                        }
                    }
                    
                    return [$item['controlId'] => $value];
                }
            })->filter();
            
            $profileData = $this->extractProfileData($userResponse);

            $profileData = array_merge($profileData, [
                'getEmails' => true,
                'getNotifications' => true,
                'userId' => $userId
            ]);

            $regions = $objectManager->search($schemaManager->find('Region'), ['take' => -1])->mapWithKeys(function($item){
                return [$item->fields['value'] => $item->id];
            });

            $profileData['regionId'] = isset($regions[$profileData['regionId']]) ? $regions[$profileData['regionId']] : null;

        } else {
            $profileData = [
                'userId' => $userId
            ];
        }
        
        try {
            return $objectManager->create($schemaManager->find(self::PROFILE_SCHEMA_NAME), $profileData);
        } catch(\Exception $e) {
            Log::debug($profileData);
        }
    }

    private function getProfile($schemaManager, $objectManager, int $userId)
    {
        $profiles = self::jsonRequest([
            'url' => app(Backend::Class)->url . "/users/$userId/profiles",
            'method' => 'GET',
            'headers' => [
                'X-Appercode-Session-Token' => app(Backend::Class)->token
            ]
        ]);
        if (is_array($profiles) && count($profiles) && isset($profiles[0])) {
            $schema = $schemaManager->find($profiles[0]['schemaId']);
            return $objectManager->find($schema, $profiles[0]['itemId']);
        } else {
            return $this->createProfile($schemaManager, $objectManager, $userId);
        }
    }

    private function getUserProcedures($schemaManager, $objectManager, $userId)
    {
        $query = json_encode(['userId' => (int) $userId]);
        $schema = $schemaManager->find('UserMedicalProcedure');
        $userProcedures = $objectManager->search($schema, ['take' => -1, 'order' => 'createdAt', 'where' => $query]);
        return $userProcedures->mapWithKeys(function ($item) {
            return [$item->fields['procedureId'] => $item->fields['date']];
        });
    }
    
    public function dashboard(Request $request)
    {
        try {
            $schemaManager = app(SchemaManager::Class);
            $objectManager = app(ObjectManager::Class);
        } catch (ClientException $e) {
            User::forgetSession(app(Backend::Class));
            return view('auth/login', [
                'selected' => 'login',
                'vkApp' => env('VK_APP'),
                'fbApp' => env('FB_APP')
            ]);
        }

        $userId = app(Backend::class)->user();
        $results = TestResult::get($userId);

        if ($userId) {
            $profile = $this->getProfile($schemaManager, $objectManager, $userId);
        }

        $regionId = isset($profile->fields['regionId']) ? $profile->fields['regionId'] : null;

        $medicalProcedures = $this->getMedicalProcedures($schemaManager, $objectManager);
        $clinics = $this->getClinicsForRegion($regionId, $schemaManager, $objectManager);

        $userProcedures = $this->getUserProcedures($schemaManager, $objectManager, $userId);

        $results->getProcedures($medicalProcedures, $clinics, $userProcedures);

        return view('dashboard', [
            'diseases' => $this->getDiseases($schemaManager, $objectManager),
            'results' => $results->lastResults(),
            'procedures' => $results->getProcedures($medicalProcedures, $clinics, $userProcedures),
            'profile' => $profile,
            'selected' => 'cabinet'
        ]);
    }

    public function settings(Request $request, SchemaManager $schemaManager, ObjectManager $objectManager)
    {
        $userId = app(Backend::class)->user();

        if ($userId) {
            $profile = $this->getProfile($schemaManager, $objectManager, $userId);
        }


        $regionList = $objectManager->search($schemaManager->find('Region'), ['take' => -1])->map(function($item) {
            return [
                'id' => $item->id,
                'title' => isset($item->fields['title']) ? $item->fields['title'] : ''
            ];
        });

        $regionList = $regionList->sortBy('title');

        $regions = [];

        $firstCities = ['1d736ba2-a721-4b7a-b08f-a836ae163a09', '36d7840d-f7b7-4607-8ec8-702667ebbe42'];

        $temp = [];

        $regionList->each(function($item, $index) use($firstCities, &$temp, &$regions) {
            if (in_array($item['id'], $firstCities)) {
                $temp[] = $item;
            }
            else {
                $regions[] = $item;
            }
        });

        $regions = $temp + $regions;

        $profile->fields['birthdate'] = isset($profile->fields['birthdate']) ? new Carbon($profile->fields['birthdate'], 'UTC') : null;

        return view('settings', [
            'profile' => $profile,
            'regions' => $regions,
            'selected' => 'settings'
        ]);
    }

    public function saveProfile(Request $request, SchemaManager $schemaManager, ObjectManager $objectManager, UserManager $userManager)
    {
        $fields = $request->except('_token');

        $userId = app(Backend::class)->user();

        $profile = $this->getProfile($schemaManager, $objectManager, $userId);

        if (!$profile->fields['isSocial'] and isset($fields['email'])) {
            try {
                app(UserManager::Class)->save($userId, ['username' => $fields['email']]);
            }
            catch (\Exception $e) {
                if ($e->getCode() == 409) {
                    $errors = new MessageBag();
                    $errors->add('email', 'Пользователь с данным E-mail уже зарегистрирован в системе');
                    return redirect()->route('settings')->withErrors($errors);
                }
            }
        }

        $profile = $objectManager->save($schemaManager->find(self::PROFILE_SCHEMA_NAME), $profile->id, $fields);

        return redirect()->route('settings');
    }

    public function changePassword(Request $request, UserManager $userManager)
    {
        $userId = app(Backend::class)->user();

        $rules = [
            'password' => 'required',
            'newpassword' => 'required',
            'confirm' => 'required|same:newpassword',
        ];

        $messages = [
            'required' => 'Поле :attribute является обязательным',
            'same' => 'Значение поля :attribute должно совпадать со значением поля :other'
        ];

        $this->validate($request, $rules, $messages);

        try {
            $userManager->changePassword($userId, [
                'oldPassword' => $request->get('password'),
                'newPassword' => $request->get('newpassword')
            ]);
        } catch (\Exception $e) {
            $errors = new MessageBag();
            $errors->add('password', 'Ваш текущий пароль указан неправильно');
            return redirect()->route('settings')->withErrors($errors);
        };

        return redirect()->route('settings');
    }

    public function subscribes(Request $request, SchemaManager $schemaManager, ObjectManager $objectManager)
    {
        $userId = app(Backend::class)->user();

        $fields = [
            'getEmails' => $request->has('subscribe'),
            'getMediaEmails' => $request->has('subscribe-media'),
            'getNotifications' => ! $request->has('notifications')
        ];

        $profile = $this->getProfile($schemaManager, $objectManager, $userId);
        $profile = $objectManager->save($schemaManager->find(self::PROFILE_SCHEMA_NAME), $profile->id, $fields);

        return redirect()->route('settings');
    }

    public function declineSubscribe(Request $request, SchemaManager $schemaManager, ObjectManager $objectManager)
    {
        $userId = app(Backend::class)->user();

        $fields = [
            'getNotifications' => ! $request->has('subscribe')
        ];

        $profile = $this->getProfile($schemaManager, $objectManager, $userId);
        $profile = $objectManager->save($schemaManager->find(self::PROFILE_SCHEMA_NAME), $profile->id, $fields);

        return redirect()->route('cabinet');
    }

    public function procedure(Request $request, SchemaManager $schemaManager, ObjectManager $objectManager)
    {
        $userId = app(Backend::class)->user();
        $procedureId = $request->input('procedure');

        $fields = [
            'userId' => $userId,
            'date' => $request->input('date'),
            'procedureId' => $procedureId
        ];

        $schema = $schemaManager->find('UserMedicalProcedure');
        $result = $objectManager->create($schema, $fields);

        $procedure = $objectManager->find($schemaManager->find('MedicalProcedure'), $procedureId);
        $periodicity = isset($procedure->fields['periodicity']) ? $procedure->fields['periodicity'] : null;

        if (! is_null($periodicity)) {
            $nextDate = new Carbon($request->input('date')); 
            $nextDate = $nextDate->addDays($periodicity)->format('d.m.Y');

            $fields = [
                'user' => $userId,
                'procedure' => $procedureId,
                'date' => $nextDate
            ];

            $schema = $schemaManager->find('Notification');
            $query = json_encode(['user' => $userId, 'procedure' => $procedureId]);
            $exists = $objectManager->search($schema, ['where' => $query]);
            if ($exists->isEmpty()) {
                $objectManager->create($schema, $fields);
            } else {
                $objectManager->save($schema, $exists->first()->id, $fields);
            }

        } else {
            $nextDate = 'Пройдена';
        }

        return response()->json([
            'nextDate' => $nextDate,
            'procedure' => $procedure
        ]);
    }
}
