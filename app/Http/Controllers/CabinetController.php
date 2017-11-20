<?php

namespace App\Http\Controllers;

use App\User;
use App\Backend;
use Carbon\Carbon;
use App\TestResult;
use Illuminate\Http\Request;
use Illuminate\Support\MessageBag;
use App\Services\UserManager;
use App\Services\ObjectManager;
use App\Services\SchemaManager;
use App\Exceptions\User\WrongCredentialsException;

class CabinetController extends Controller
{
    const PROFILE_SCHEMA_NAME = 'UserProfiles';

    public function messages()
    {
        return [
            'newpassword.required' => 'A password is required',
            'repeatpassword.required'  => 'A password is required',
        ];
    }

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
        $query = json_encode(['region' => $region]);
        return $objectManager
            ->search($schemaManager->find('Clinic'), ['where' => $query, 'take' => -1])
            ->mapWithKeys(function ($item) {
                return [$item->id => $item->fields];
            });
    }
    
    public function dashboard(Request $request, SchemaManager $schemaManager, ObjectManager $objectManager)
    {
        $userId = session(app(Backend::class)->code . '-id');
        $results = TestResult::get($userId);

        $profile = $this->getProfile($schemaManager, $objectManager, $request);
        $region = $profile->fields['region'] ?? 'Ленинградская область';

        $medicalProcedures = $this->getMedicalProcedures($schemaManager, $objectManager);
        $clinics = $this->getClinicsForRegion($region, $schemaManager, $objectManager);

        return view('dashboard', [
            'diseases' => $this->getDiseases($schemaManager, $objectManager),
            'procedures' => $results->getProcedures($medicalProcedures, $clinics),
            'results' => $results,
            'profile' => $this->getProfile($schemaManager, $objectManager, $request),
            'selected' => 'cabinet'
        ]);
    }

    private function getProfile($schemaManager, $objectManager, $request)
    {
        if (isset($request->profile)) {
            return $request->profile;
        } else {
            $schema = $schemaManager->find(self::PROFILE_SCHEMA_NAME);
            return $objectManager->find($schema, $request->profileId);
        }
    }

    public function settings(Request $request, SchemaManager $schemaManager, ObjectManager $objectManager)
    {
        $profile = $this->getProfile($schemaManager, $objectManager, $request);

        $profile->fields['birthdate'] = isset($profile->fields['birthdate']) ? new Carbon($profile->fields['birthdate'], 'UTC') : null;

        return view('settings', [
            'profile' => $profile,
            'selected' => 'settings'
        ]);
    }

    public function saveProfile(Request $request, SchemaManager $schemaManager, ObjectManager $objectManager)
    {
        $fields = $request->except('_token');

        $profile = $this->getProfile($schemaManager, $objectManager, $request);
        $profile = $objectManager->save($schemaManager->find(self::PROFILE_SCHEMA_NAME), $profile->id, $fields);

        return redirect()->route('settings');
    }

    public function changePassword(Request $request, UserManager $userManager)
    {
        $userId = session(app(Backend::class)->code . '-id');

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
        $fields = [
            'getEmails' => $request->has('subscribe'),
            'getNotifications' => $request->has('notifications')
        ];

        $profile = $this->getProfile($schemaManager, $objectManager, $request);
        $profile = $objectManager->save($schemaManager->find(self::PROFILE_SCHEMA_NAME), $profile->id, $fields);

        return redirect()->route('settings');
    }

    public function declineSubscribe(Request $request, SchemaManager $schemaManager, ObjectManager $objectManager)
    {
        $fields = [
            'getNotifications' => ! $request->has('subscribe')
        ];

        $profile = $this->getProfile($schemaManager, $objectManager, $request);
        $profile = $objectManager->save($schemaManager->find(self::PROFILE_SCHEMA_NAME), $profile->id, $fields);

        return redirect()->route('cabinet');
    }
}
