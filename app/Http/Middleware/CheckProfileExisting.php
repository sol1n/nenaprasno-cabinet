<?php

namespace App\Http\Middleware;

use Closure;
use App\Backend;
use App\TestResult;
use App\Services\ObjectManager;
use App\Services\SchemaManager;

class CheckProfileExisting
{
    const PROFILE_SCHEMA_NAME = 'UserProfiles';

    public function __construct()
    {
        $this->userId = session(app(Backend::Class)->code . '-id');
        $this->profileId = session('profile-id');

        $this->schemaManager = app(SchemaManager::Class);
        $this->objectManager = app(ObjectManager::Class);

        $this->profileSchema = $this->schemaManager->find(self::PROFILE_SCHEMA_NAME);
    }

    private function createProfile()
    {
        return $this->objectManager->create($this->profileSchema, [
            'userId' => $this->userId
        ]);
    }

    private function fetchProfile()
    {
        $profile = $this->objectManager->search($this->profileSchema, ['where' => json_encode(['userId' => $this->userId])]);

        if ($profile->isEmpty()) {
            return false;
        } else {
            return $profile;
        }
    }

    private function getProfile()
    {
        if ($profile = $this->fetchProfile())
        {
            return $profile;
        } else {
            return $this->createProfile();
        }

        return $profile;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if ($request->session()->has('profile-id')) {
            $request->profileId = $request->session()->get('profile-id');
        } else {
            $profile = $this->getProfile()->first();
            $request->profile = $profile;
            $request->profileId = $profile->id;

            $request->session()->put('profile-id', $profile->id);
        }
       
        return $next($request);
    }
}
