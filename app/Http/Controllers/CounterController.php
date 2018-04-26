<?php

namespace App\Http\Controllers;

use App;
use App\User;
use App\Backend;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Services\ObjectManager;
use App\Services\SchemaManager;
use App\Helpers\AdminTokens;
use Illuminate\Support\Facades\Cache;


class CounterController extends Controller
{
    const OLD_USERS_COUNT = 284040;
    const CACHE_KEY = 'users-count';

    private $schemaManager;
    private $objectManager;

    private function getData()
    {
        $tokens = new AdminTokens();
        $backend = $tokens->getSession('notnap');
        
        App::instance(Backend::class, $backend);

        $this->schemaManager = new SchemaManager();
        $this->objectManager = new ObjectManager();

        $schema = $this->schemaManager->find('UserProfiles');

        $date = new Carbon('2018-04-02');
        $users = $this->objectManager->count($schema, ['search' => ['createdAt' => ['$gt' => $date->toAtomString()]]]);

        return $users + self::OLD_USERS_COUNT;
    }

    public function index()
    {
        if (Cache::has(self::CACHE_KEY)) {
            $users = Cache::get(self::CACHE_KEY);
        } else {
            $users = $this->getData();
            Cache::put(self::CACHE_KEY, $users, 20);
        }
        return response()->json(['users' => $users]);
    }
}
