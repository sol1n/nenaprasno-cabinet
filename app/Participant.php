<?php

namespace App;


use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;

class Participant
{
    /**
     * @var string
     */
    public $id;

    /**
     * @var string
     */
    public $meetingId;

    /**
     * @var integer
     */
    public $statusId;

    /**
     * @var integer
     */
    public $userId;

    /**
     * @var User
     */
    public $user;

    /**
     * @var Carbon
     */
    public $createdAt;
    /**
     * @var Carbon
     */
    public $updatedAt;

    /**
     * @var bool
     */
    public $isDeleted;

    /**
     * @var Backend
     */
    private $backend;

    CONST STATUS_PENDING = 1;
    CONST STATUS_ACCEPTED = 2;
    CONST STATUS_CANCELLED = 3;

    public function __construct(array $data = [], Backend $backend = null)
    {
        $this->id = isset($data['id']) ? $data['id'] : '';
        $this->statusId = isset($data['statusId']) ? (int)$data['statusId'] : null;
        $this->meetingId = isset($data['meetingId']) ? $data['meetingId'] : null;
        $this->userId = isset($data['userId']) ? (is_array($data['userId']) ? (int)$data['userId']['id'] : (int)$data['userId']) : null;
        $this->isDeleted = isset($data['isDeleted']) ? (bool)$data['isDeleted'] : null;
        $this->createdAt = isset($data['created_at']) ? Carbon::parse($data['created_at']) : null;
        $this->updatedAt = isset($data['updatedAt']) ? Carbon::parse($data['updatedAt']) : null;
        if (isset($data['userId']) and is_array($data['userId'])) {
            $user =  new User();
            $user->id = $data['userId']['id'];
            $user->username = $data['userId']['username'];
            $this->user = $user;
        }
        else {
            $this->user = isset($data['user']) ? $data['user'] : null;
        }
        if ($backend) {
            $this->setBackend($backend);
        }
        return $this;
    }

    public static function forMeeting(Backend $backend, string $meetingId, bool $withUserProfiles = false): Collection
    {
        $params['where'] = json_encode(['meetingId' => $meetingId]);
        return static::fetch($backend, $params, $withUserProfiles);
    }

    public static function forMeetings(Backend $backend, Collection $list, bool $withUserProfiles = false): Collection
    {
        $ids = $list->pluck('id')->toArray();
        $params['where'] = ['id' => ['$in' => $ids]];
        $participants = static::fetch($backend, $params, $withUserProfiles);
        foreach ($list as $item) {
            /**
             * @var Meeting $item
             */
            $item->participants = $participants->where('meetingId', $item->id)->values();
        }
        return $list;
    }

    public static function fetch(Backend $backend, array $params, bool $withUserProfiles = false): Collection
    {
        $query = [];
        if ($params) {
            $query = $params;
        }

        if (!isset($query['include'])) {
            $query['include'] = json_encode(["id", "createdAt", "updatedAt", "ownerId", "meetingId", "statusId", ["userId" => ["id", "username"]]]);
        }

        $query = http_build_query($query);

        $client = new Client();
        try {
            $r = $client->get($backend->url . 'objects/Participants' . ($query ? '?'. $query : ''), ['headers' => [
                'X-Appercode-Session-Token' => $backend->token
            ]]);
        } catch (RequestException $e) {
            throw new \Exception('Error while getting participants list');
        };

        $data = static::decode($r->getBody()->getContents());

        $result = new Collection();
        $userIds = [];
        foreach ($data as $datum) {
            $item = new static($datum, $backend);
            $result->push($item);
            $userIds[] = $item->userId;
        }

        if ($withUserProfiles and $userIds) {
            $shortViews = Meeting::getProfiles($backend, $userIds);
            foreach ($result as $item) {
                if (isset($shortViews[$item->userId])) {
                    $item->user = static::compileUser($item->user, $shortViews[$item->userId]);
                }
            }
//            $settings = new Settings($backend);
//            $profileSchemas = $settings->getProfileSchemas();
//            $shortViews = [];
//            foreach ($profileSchemas as $profileSchema) {
//                $queryProfile['search'] = ['userId' => ['$in' => $userIds]];
//                $objects = Object::list($profileSchema, $backend, $queryProfile);
//                $userField = $profileSchema->getUserLinkField();
//                foreach ($objects as $object) {
//                    if ($object->fields[$userField['name']]) {
//                        $userIds[] = $object->fields[$userField['name']];
//                        $shortViews[$object->fields[$userField['name']]] = $object->shortView();
//                    }
//                }
//                foreach ($result as $item) {
//                    if (isset($shortViews[$item->userId])) {
//                        $item->user = static::compileUser($item->user, $shortViews[$item->userId]);
//                    }
//                }
//            }

        }


        return $result;
    }


    public static function get(Backend $backend, string $meetingId, int $userId, bool $withProfile = false): Participant
    {
        /**
         * @var Participant $result
         */
        $result = null;
        $params['where'] = json_encode(['userId' => $userId, 'meetingId' => $meetingId]);
        $queryResult = static::fetch($backend, $params, $withProfile);
        if ($queryResult->isNotEmpty()) {
            $result = $queryResult->first();
            $result->setBackend($backend);
        }
        return $result;
    }

    public static function getById(Backend $backend, string $participantId, bool $withProfile = false): Participant
    {
        /**
         * @var Participant $result
         */
        $result = null;
        $params['where'] = json_encode(['id' => $participantId]);
        $queryResult = static::fetch($backend, $params, $withProfile);
        if ($queryResult->isNotEmpty()) {
            $result = $queryResult->first();
            $result->setBackend($backend);
        }
        return $result;
    }


    public static function decode($data) {
        return json_decode($data, 1);
    }

    private function setBackend(Backend &$backend): Participant
    {
        $this->backend = $backend;
        return $this;
    }

    public function toArrayForUpdate() {
        $result = [
            'meetingId' => $this->meetingId,
            'userId' => (int)$this->userId,
            'statusId' => (int)$this->statusId,
        ];
        if ($this->id) {
            $result['id'] = $this->id;
        }
        return $result;
    }

    public function save(): Participant
    {
        $backend = $this->backend;
        if (isset($backend->token)) {
            $client = new Client;
            try {
                if ($this->id) {
                    $r = $client->put($backend->url . 'objects/Participants/' . $this->id, [
                        'headers' => ['X-Appercode-Session-Token' => $backend->token],
                        'json' => $this->toArrayForUpdate()
                    ]);
                }
                else{
                    $r = $client->post($backend->url . 'objects/Participants', [
                        'headers' => ['X-Appercode-Session-Token' => $backend->token],
                        'json' => $this->toArrayForUpdate()
                    ]);
                }
            } catch (RequestException $e) {
                throw new \Exception('Participant save error');
            };
        } else {
            throw new \Exception('No backend provided');
        }

        $data = static::decode($r->getBody()->getContents(), 1);
        $participant = new Participant($data, $backend);

        return $participant;
    }

    public static function compileUser($user, $shortView)
    {
        return [
            'id' => $user->id,
            'username' => $user->username,
            'shortView' => $shortView
        ];
    }

    public function delete()
    {
        $client = new Client;

        $r = $client->delete($this->backend->url . 'objects/Participants/' . $this->id, ['headers' => [
            'X-Appercode-Session-Token' => $this->backend->token
        ]]);

        return $this;
    }

    public static function participantsForInvitation(Backend $backend, array $meetingIds, int $userId, $withProfile = false)
    {
        $result = null;
        $params['where'] = json_encode(['userId' => $userId, 'meetingId' => ['$in' => $meetingIds]]);
        $queryResult = static::fetch($backend, $params, $withProfile);
        if ($queryResult->isNotEmpty()) {
            $result = [];
            foreach ($queryResult as $item) {
                $result[$item->meetingId] = $item;
            }
            $result = collect($result);
        }
        return $result;
    }

    public static function changeStatus(Backend $backend, string $id, int $status)
    {
        $headers = [
            'X-Appercode-Session-Token' => $backend->token
        ];

        $client = new Client;
        try {
            $r = $client->put($backend->url . 'objects/Participants/' . $id, [
                'headers' => $headers,
                'json' => [
                    'statusId' => $status
                ]
            ]);
        } catch (RequestException $e) {
            throw new \Exception('Participant update error');
        };

        return true;
    }

    public function userShortView()
    {
        $result = '';
        if ($this->user) {
            if (is_array($this->user)) {
                if (isset($this->user['shortView']) and $this->user['shortView']) {
                    $result = $this->user['shortView'];
                } else {
                    $result = $this->user['username'];
                }
            }
            else{
                $result = $this->user->username;
            }
        }
        return $result;
    }

}