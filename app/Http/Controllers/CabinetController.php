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

    private function createProfile($schemaManager, $objectManager, $userId)
    {
        return $objectManager->create($schemaManager->find(self::PROFILE_SCHEMA_NAME), [
            'userId' => $userId
        ]);
    }

    private function getProfile($schemaManager, $objectManager, int $userId)
    {
        $profile = $objectManager->search($schemaManager->find(self::PROFILE_SCHEMA_NAME), ['where' => json_encode(['userId' => $userId])]);

        if ($profile->isEmpty()) {
            return $this->createProfile($schemaManager, $objectManager, $userId);
        } else {
            return $profile->first();
        }
    }

    private function getUserProcedures($schemaManager, $objectManager, $userId)
    {
        $query = json_encode(['userId' => $userId]);
        $schema = $schemaManager->find('UserMedicalProcedure');
        $userProcedures = $objectManager->search($schema, ['take' => -1, 'order' => 'createdAt', 'where' => $query]);
        return $userProcedures->mapWithKeys(function ($item) {
            return [$item->fields['procedureId'] => $item->fields['date']];
        });
    }
    
    public function dashboard(Request $request, SchemaManager $schemaManager, ObjectManager $objectManager)
    {
        $userId = session(app(Backend::class)->code . '-id');
        $results = TestResult::get($userId);

        if ($userId) {
            $profile = $this->getProfile($schemaManager, $objectManager, $userId);
        }

        $region = $profile->fields['region'] ?? 'Ленинградская область';

        $medicalProcedures = $this->getMedicalProcedures($schemaManager, $objectManager);
        $clinics = $this->getClinicsForRegion($region, $schemaManager, $objectManager);

        $userProcedures = $this->getUserProcedures($schemaManager, $objectManager, $userId);

        return view('dashboard', [
            'diseases' => $this->getDiseases($schemaManager, $objectManager),
            'procedures' => $results->getProcedures($medicalProcedures, $clinics, $userProcedures),
            'results' => $results,
            'profile' => $profile,
            'selected' => 'cabinet'
        ]);
    }

    public function settings(Request $request, SchemaManager $schemaManager, ObjectManager $objectManager)
    {
        $userId = session(app(Backend::class)->code . '-id');

        if ($userId) {
            $profile = $this->getProfile($schemaManager, $objectManager, $userId);
        }

        $profile->fields['birthdate'] = isset($profile->fields['birthdate']) ? new Carbon($profile->fields['birthdate'], 'UTC') : null;

        return view('settings', [
            'profile' => $profile,
            'selected' => 'settings'
        ]);
    }

    public function saveProfile(Request $request, SchemaManager $schemaManager, ObjectManager $objectManager)
    {
        $fields = $request->except('_token');
        $userId = session(app(Backend::class)->code . '-id');

        $profile = $this->getProfile($schemaManager, $objectManager, $userId);
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
        $userId = session(app(Backend::class)->code . '-id');
        
        $fields = [
            'getEmails' => $request->has('subscribe'),
            'getNotifications' => $request->has('notifications')
        ];

        $profile = $this->getProfile($schemaManager, $objectManager, $userId);
        $profile = $objectManager->save($schemaManager->find(self::PROFILE_SCHEMA_NAME), $profile->id, $fields);

        return redirect()->route('settings');
    }

    public function declineSubscribe(Request $request, SchemaManager $schemaManager, ObjectManager $objectManager)
    {
        $userId = session(app(Backend::class)->code . '-id');

        $fields = [
            'getNotifications' => ! $request->has('subscribe')
        ];

        $profile = $this->getProfile($schemaManager, $objectManager, $userId);
        $profile = $objectManager->save($schemaManager->find(self::PROFILE_SCHEMA_NAME), $profile->id, $fields);

        return redirect()->route('cabinet');
    }

    public function procedure(Request $request, SchemaManager $schemaManager, ObjectManager $objectManager)
    {
        $userId = session(app(Backend::class)->code . '-id');

        $fields = [
            'userId' => $userId,
            'date' => $request->input('date'),
            'procedureId' => $request->input('procedure')
        ];

        $schema = $schemaManager->find('UserMedicalProcedure');
        $result = $objectManager->create($schema, $fields);

        $procedure = $objectManager->find($schemaManager->find('MedicalProcedure'), $request->input('procedure'));
        $periodicity = $procedure->fields['periodicity'] ?? null;

        if (! is_null($periodicity)) {
            $nextDate = new Carbon($request->input('date')); 
            $nextDate = $nextDate->addDays($periodicity)->format('d.m.Y');
        } else {
            $nextDate = 'Пройдена';
        }

        return response()->json([
            'nextDate' => $nextDate,
            'procedure' => $procedure
        ]);
    }
}
