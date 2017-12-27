<?php

namespace App\Console\Commands;

use App\Backend;
use App\Exceptions\User\UserNotFoundException;
use App\Object;
use App\Services\ObjectManager;
use App\Services\SchemaManager;
use App\User;
use Illuminate\Console\Command;
use Illuminate\Pagination\Paginator;
use Mockery\Exception;

class ImportUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:users';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import users';

    protected $backend;

    private function initBackend()
    {
        $this->backend = new Backend(env('APPERCODE_DEFAULT_BACKEND'), env('APPERCODE_SERVER'));
        $user = User::Login($this->backend, [
            'login' => env('APPERCODE_LOGIN'),
            'password' => env('APPERCODE_PASSWORD')
        ], false);
        $this->backend->token = $user->token();
        app()->instance(Backend::class, $this->backend);
    }

    /**
     * @param int $length
     * @return bool|string
     */
    function random_password( $length = 8 ) {
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$";
        $password = substr( str_shuffle( $chars ), 0, $length );
        return $password;
    }

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        $this->initBackend();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $objectManager = new ObjectManager();
        $schemaManager = new SchemaManager();
//        $file = file_get_contents(base_path('vagrant/1.txt'));
//        $ids = explode("\n", $file);

        $schema = $schemaManager->find('UserProfiles');

//        foreach ($ids as $id) {
//            if ($id) {
//                try {
//                    $user = User::get($id, $this->backend);
//                    if ($user) {
//                        $profile = Object::list($schema, $this->backend, ['search' => ['userId' => $user->id]]);
//                        if ($profile->count() > 0) {
//                            $profile->first()->delete($this->backend);
//                        }
//                        $user->delete($this->backend);
//                        $this->info($user->id);
//                    }
//                }
//                catch (UserNotFoundException $e) {
//
//                }
//            }
//        }
//        die('done');


        $regions = $objectManager->search($schemaManager->find('Region'), ['take' => -1])->map(function($item) {
            return [
                'id' => $item->id,
                'title' => isset($item->fields['title']) ? $item->fields['title'] : ''
            ];
        });

        $regions = collect($regions);

        $this->info('start');
        $file = file_get_contents(base_path('vagrant/NNP3_CLIENT.csv'));
        $rows = explode("\n", $file);
        unset($rows[0]);
        $count =1;
        foreach ($rows as $index => $row) {
           if ($row) {
               $item = explode(';', $row);
               $fio = str_replace('"','', $item[4]);
               $phone = str_replace('"','', $item[8]);
               $email = str_replace('"','',$item[9]);
               $region = str_replace('"','',$item[16]);

               $regionObject = ($regions->filter(function($value, $key) use($region) {
                   return $region and mb_stripos($value['title'], $region) !== false;
               }));

               try {
                   $user = User::create([
                       'username' => $email,
                       'password' => $this->random_password()
                   ], $this->backend);
               }
               catch (UserCreateException $e) {
                   if ($e->getMessage() != 'Conflict when user creation') {
                       break;
                   }
               }

               if ($user->id) {
                   $data = [
                       'userId' => $user->id,
                       'email' => $email,
                       'phoneNumber' => $phone ?? '',
                       'lastName' => $fio ?? ''
                   ];

                   if ($regionObject->count() > 0) {
                       $data['regionId'] = $regionObject->first()['id'];
                   }

                   $objectManager->create($schema, $data);
               }
               $this->info($count . ' - ' . $user->id);
               $count++;

               file_put_contents(base_path('vagrant/1.txt'), $user->id . "\n", FILE_APPEND);
           }
        }
        $this->info('done');
    }
}
