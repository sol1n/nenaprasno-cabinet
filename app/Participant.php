<?php

namespace App;


use App\Traits\Models\AppercodeRequest;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Mockery\Exception;

class Participant
{

    use AppercodeRequest;

    /**
     * @var string
     */
    public $id;

//    /**
//     * @var string
//     */
//    public $meetingId;

    /**
     * @var string
     */
    public $status;

    /**
     * @var integer
     */
    public $userId;

    /**
     * @var string
     */
    public $userInfo;

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

    CONST STATUS_PENDING = "pending";
    CONST STATUS_ACCEPTED = "accepted";
    CONST STATUS_CANCELLED = "cancelled";

    public function __construct(array $data = [], Backend $backend = null)
    {
        $this->id = isset($data['id']) ? $data['id'] : '';
        $this->status = isset($data['status']) ? $data['status'] : '';
        $this->userInfo = isset($data['userInfo']) ? $data['userInfo'] : '';
//        $this->meetingId = isset($data['meetingId']) ? $data['meetingId'] : null;
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

    public static function forMeeting(Backend $backend, string $meetingId, bool $withUserProfiles = false, $language = 'en'): Collection
    {
        $params['where'] = json_encode(['meetingId' => $meetingId]);
        return static::fetch($backend, $params, $withUserProfiles, $language);
    }

    public static function forMeetings(Backend $backend, Collection $list, bool $withUserProfiles = false, $language = 'en'): Collection
    {
        $ids = [];
        if  ($ids) {
            $params['where'] = ['id' => ['$in' => $ids]];
            $participants = static::fetch($backend, $params, $withUserProfiles, $language);
            foreach ($list as $item) {
                /**
                 * @var Meeting $item
                 */
                $item->participants = $participants->where('meetingId', $item->id)->values();
            }
        }
        return $list;
    }

    public static function fetch(Backend $backend, array $params, bool $withUserProfiles = false, $language = 'en'): Collection
    {
        $query = [];
        if ($params) {
            $query = $params;
        }

        if (!isset($query['include'])) {
            $query['include'] = json_encode(["id", "createdAt", "updatedAt", "ownerId", "meetingId", "status", ["userId" => ["id", "username"]]]);
        }

        $query = http_build_query($query);

        $data = static::jsonRequest([
           'method' => 'GET',
           'headers' => ['X-Appercode-Session-Token' => $backend->token],
           'url' => $backend->url . 'objects/Participants' . ($query ? '?'. $query : '')
        ]);

        $result = new Collection();
        $userIds = [];
        foreach ($data as $datum) {
            $item = new static($datum, $backend);
            $result->push($item);
            $userIds[] = $item->userId;
        }

        if ($withUserProfiles and $userIds) {
            $shortViews = Meeting::getProfiles($backend, $userIds, $language);
            foreach ($result as $item) {
                if (isset($shortViews[$item->userId])) {
                    $item->user = static::compileUser($item->user, $shortViews[$item->userId]);
                }
            }

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
            'userId' => (int)$this->userId,
            'status' => $this->status,
            'userInfo' => $this->userInfo,
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
            if ($this->id) {
                $data = static::jsonRequest([
                    'method' => 'PUT',
                    'headers' => ['X-Appercode-Session-Token' => $backend->token],
                    'json' => $this->toArrayForUpdate(),
                    'url' => $backend->url . 'objects/Participants/' . $this->id
                ]);
            }
            else{
                $data = static::jsonRequest([
                    'method' => 'POST',
                    'headers' => ['X-Appercode-Session-Token' => $backend->token],
                    'json' => $this->toArrayForUpdate(),
                    'url' => $backend->url . 'objects/Participants'
                ]);
            }

        } else {
            throw new \Exception('No backend provided');
        }

        $participant = new Participant($data, $backend);

        return $participant;
    }

    public static function compileUser($user, $shortView)
    {
        return [
            'id' => $user->id,
            'username' => $user->username,
            'shortView' => $shortView['shortView'],
            'company' => $shortView['company'],
            'position' => $shortView['position'],
            'photoFileId' => $shortView['photoFileId'],
        ];
    }

    public function delete()
    {
        static::request([
            'method' => 'DELETE',
            'headers' => ['X-Appercode-Session-Token' => $this->backend->token],
            'url' => $this->backend->url . 'objects/Participants/' . $this->id
        ]);

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

    public static function changeStatus(Backend $backend, string $id, string $status)
    {
        $headers = [
            'X-Appercode-Session-Token' => $backend->token
        ];

        static::request([
            'method' => 'PUT',
            'headers' => $headers,
            'json' => [
                'status' => $status
            ],
            'url' => $backend->url . 'objects/Participants/' . $id
        ]);

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