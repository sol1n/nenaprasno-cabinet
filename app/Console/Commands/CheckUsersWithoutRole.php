<?php

namespace App\Console\Commands;

use App;
use App\Backend;
use App\Helpers\AdminTokens;
use App\Services\UserManager;
use App\Services\SchemaManager;
use App\Services\ObjectManager;
use Illuminate\Console\Command;

class CheckUsersWithoutRole extends Command
{
    const PROJECT = 'notnap';
    const DEFAULT_ROLE = 'Common';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Checks for users who do not have a role';

    private function buildManagers($project)
    {
        $adminTokens = new AdminTokens;
        $backend = $adminTokens->getSession($project);
        App::instance(Backend::class, $backend);
        
        $this->schemaManager = new SchemaManager();
        $this->objectManager = new ObjectManager();
        $this->userManager = new UserManager();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->buildManagers(self::PROJECT);
        $users = $this->userManager->search([
            'take' => -1,
            'where' => json_encode(['roleId' => ['$exists' => false]])
        ]);
        $count = $users->count();
        $counter = 0;
        $users->each(function($item) use (&$counter, $count) {
            $counter++;
            $this->userManager->save($item->id, ['roleId' => self::DEFAULT_ROLE]);
            $this->info("Обработано пользователей: $counter из $count");
        });
    }
}
