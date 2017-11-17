<?php

namespace App\Http\Controllers;

use App\User;
use App\Backend;
use App\TestResult;
use Illuminate\Http\Request;
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
    		->mapWithKeys(function($item) {
    			return [$item->id => $item->fields];
    	});
	}

	private function getMedicalProcedures($schemaManager, $objectManager)
	{
    	return $objectManager
    		->search($schemaManager->find('MedicalProcedure'), ['take' => -1])
    		->mapWithKeys(function($item) {
    			return [$item->id => array_merge($item->fields, ['id' => $item->id])];
    	});
	}

	private function getClinicsProcedures($schemaManager, $objectManager)
	{
    	return $objectManager
    		->search($schemaManager->find('MedicalProcedure'), ['take' => -1])
    		->mapWithKeys(function($item) {
    			return [$item->id => $item->fields];
    	});
	}

    private function getClinicsForRegion($region, $schemaManager, $objectManager)
    {
        $query = json_encode(['region' => $region]);
        return $objectManager
            ->search($schemaManager->find('Clinic'), ['where' => $query, 'take' => -1])
            ->mapWithKeys(function($item) {
                return [$item->id => $item->fields];
        });
    }
	
    public function dashboard(Request $request, SchemaManager $schemaManager, ObjectManager $objectManager)
    {
    	$userId = session(app(Backend::Class)->code . '-id');
    	$results = TestResult::get($userId);

        $profile = $this->getProfile($schemaManager, $objectManager, $request);
        $region = $profile->fields['region'] ?? 'Ленинградская область';

        $medicalProcedures = $this->getMedicalProcedures($schemaManager, $objectManager);
    	$clinics = $this->getClinicsForRegion($region, $schemaManager, $objectManager);

        return view('dashboard', [
        	'diseases' => $this->getDiseases($schemaManager, $objectManager),
        	'procedures' => $results->getProcedures($medicalProcedures, $clinics),
        	'results' => $results,
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
}
