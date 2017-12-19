<?php

namespace App\Console\Commands;

use Mail;
use App\User;
use App\Backend;
use Carbon\Carbon;
use App\Services\SchemaManager;
use App\Services\ObjectManager;
use Illuminate\Console\Command;

class Notification extends Command
{
    const LAG = 3;

    private $backend;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notification:emails';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sends email notification for users';

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
        $this->info('started');
        
        $now = new Carbon;
        $from = $now->addDays(self::LAG)->toAtomString();
        $now = new Carbon;
        $to = $now->addDays(self::LAG + 1)->toAtomString();
        $schema = app(SchemaManager::Class)->find('Notification');

        $query = json_encode(['date' => ['$gte' => $from, '$lt' => $to]]);

        $notifications = app(ObjectManager::Class)->search($schema, ['take' => -1, 'where' => $query]);

        $procedures = app(ObjectManager::Class)->search(app(SchemaManager::Class)->find('MedicalProcedure'), ['take' => -1]);
        $procedures = $procedures->mapWithKeys(function($item){
            return [$item->id => $item->fields['name'] ?? ''];
        });

        $notifications->each(function($item) use (&$grouped, $procedures) {
            $userId = $item->fields['user'] ?? null;
            $procedureId = $item->fields['procedure'] ?? null;
            if (!is_null($userId) && !is_null($procedureId)) {
                $grouped[$userId][] = $procedures[$procedureId];
            }
        });

        foreach ($grouped as $userId => $procedures)
        {
            Mail::send('emails.notify', ['procedures' => $procedures], function ($message) {
                $message->subject('Notification');
                $message->from('no-reply@konferenza.com', 'Nenaprasno');
                $message->to('is.perfect.possible@gmail.com');
            });
        }

        $this->info('all done');
    }
}
