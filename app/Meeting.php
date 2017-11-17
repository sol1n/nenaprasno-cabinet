<?php
/**
 * Created by PhpStorm.
 * User: tsyrya
 * Date: 04/11/2017
 * Time: 09:59
 */

namespace App;


use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Symfony\Component\Finder\Iterator\PathFilterIterator;

class Meeting
{
    /**
     * @var string
     */
    public $id;
    /**
     * @var integer
     */
    public $creatorId;
    /**
     * @var string
     */
    public $conferenceId;
    /**
     * @var Carbon
     */
    public $date;
    /**
     * @var string
     */
    public $topic;
    /**
     * @var Collection
     */
    public $participants;
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
     * @var Schema
     */
    public $schema;

    public $creator;

    /**
     * @var Backend
     */
    private $backend;

    public function __construct(array $data = [], Backend $backend = null)
    {
        $this->id = isset($data['id']) ? $data['id'] : null;
        if (isset($data['creatorId']) and is_array($data['creatorId'])) {
            $this->creatorId = isset($data['creatorId']['id']) ? $data['creatorId']['id'] : '';
            $this->creator = $data['creatorId'];
        }
        else {
            $this->creatorId = isset($data['creatorId']) ? (int)$data['creatorId'] : '';
            $this->creator = [];
        }
        $this->conferenceId = isset($data['conferenceId']) ? $data['conferenceId'] : '';
        $this->date = isset($data['date']) ? Carbon::parse($data['date']) : null;
        $this->topic = isset($data['topic']) ? $data['topic'] : '';
        $this->participants = isset($data['participants']) ? collect($data['participants']) : new Collection();
        $this->isDeleted = isset($data['isDeleted']) ? (bool)$data['isDeleted'] : null;
        $this->createdAt = isset($data['created_at']) ? Carbon::parse($data['created_at']) : null;
        $this->updatedAt = isset($data['updatedAt']) ? Carbon::parse($data['updatedAt']) : null;
        if ($backend) {
            $this->setBackend($backend);
        }
        return $this;
    }

    public function trimDate($date)
    {
        return mb_substr($date, 0, mb_strlen($date)-6);
    }

    public function toArrayForUpdate() {
        $result = [
            'creatorId' => (int) $this->creatorId,
            'conferenceId' => $this->conferenceId,
            'date' => $this->trimDate($this->date->toAtomString()),
            'topic' => $this->topic
        ];
        if ($this->id) {
            $result['id'] = $this->id;
        }
        return $result;
    }


    private function setBackend(Backend &$backend): Meeting
    {
        $this->backend = $backend;
        return $this;
    }

    public function loadParticipants()
    {
        $this->participants = Participant::forMeeting($this->backend, $this->id, true);
    }

    public static function getCreatedMeetings(Backend $backend, string $conferenceId, int $creatorId, bool $withParticipant =  false): Collection
    {
        $params = [];
        $where = [];
        if ($conferenceId) {
            $where['conferenceId'] = $conferenceId;
        }
        if ($creatorId) {
            $where['creatorId'] = $creatorId;
        }
        if ($where) {
            $params['where'] = json_encode($where);
        }
        $result = static::fetch($backend, $params);
        if ($withParticipant) {
            $result = Participant::forMeetings($backend, $result, true);
        }
        return $result;
    }

    public static function getInvitations(Backend $backend, String $conferenceId, int $userId, bool $withProfiles =  false): Collection
    {
        //[  {  "meetingId":['id','createdAt','updatedAt','conferenceId', {'creatorId': ['id', 'username']},'date',"topic"]   } ]
        $result = new Collection();
        $query['include'] = json_encode([["meetingId" => ['id','createdAt','updatedAt','conferenceId', ['creatorId' => ['id', 'username']],'date',"topic"]]]);
        $query['where'] = json_encode(['userId' => $userId,
                "meetingId" => [
                        '$inQuery' => [
                            "where" => [
                                "conferenceId" => $conferenceId
                            ],
                      "schema" => "Meetings"
                    ]
                ]
            ]);

        $query = http_build_query($query);

        $client = new Client();
        try {
            $r = $client->get($backend->url . 'objects/Participants' . ($query ? '?'. $query : ''), ['headers' => [
                'X-Appercode-Session-Token' => $backend->token
            ]]);
        } catch (RequestException $e) {
            throw new \Exception('Error while getting invitation list');
        };

        $data = static::decode($r->getBody()->getContents());

        $userIds = [];
        $meetingIds=[];

        foreach ($data as $datum) {
            $meeting = new Meeting($datum['meetingId'], $backend);
            $result->push($meeting);
            $userIds[] = $meeting->creatorId;
            $meetingIds[] = $meeting->id;
        }

        if ($meetingIds) {
            $participants = Participant::participantsForInvitation($backend, $meetingIds, $userId, false);
            foreach ($result as $item) {
                $participant = $participants->get($item->id);
                if ($participant) {
                    $item->participants->push($participant);
                }
            }
        }

        if ($withProfiles and $userIds) {
            $shortViews = static::getProfiles($backend, $userIds);
            foreach ($result as $item) {
                if (isset($shortViews[$item->creatorId])) {
                    $item->creator['shortView'] = $shortViews[$item->creatorId];
                }
            }
        }

        return $result;

    }

    public static function getProfiles(Backend $backend, array $userIds)
    {
        $settings = new Settings($backend);
        $profileSchemas = $settings->getProfileSchemas();
        $shortViews = [];
        /**
         * @var Schema $profileSchema
         */
        $profileSchema = $profileSchemas->first();
        if ($profileSchema) {
            $queryProfile['search'] = ['userId' => ['$in' => $userIds]];
//            $fields = $profileSchema->fields;
//            $shortViewFields = $profileSchema->getShortViewFields();
//            $include = [];
//            foreach ($fields as $field) {
//                if (in_array($field['name'], $shortViewFields)) {
//                    var_dump($field);
//                    if (mb_strpos($field['type'], 'ref')) {
//                        $code = str_replace('ref ', '', $field['type']);
//                        dd($code);
//                    }
//                    else{
//                        $include[] = $field['name'];
//                    }
//                }
//            }
//            dd($include);
            $objects = Object::list($profileSchema, $backend, $queryProfile);
            $userField = $profileSchema->getUserLinkField();
            foreach ($objects as $object) {
                if ($object->fields[$userField['name']]) {
                    $userIds[] = $object->fields[$userField['name']];
                    $shortViews[$object->fields[$userField['name']]] = $object->shortView();
                }
            }
        }

        return $shortViews;

    }

    public static function decode($data) {
        return json_decode($data, 1);
    }


    private static function fetch(Backend $backend, array $params = []): Collection
    {
        $query = [];
        if ($params) {
            $query = $params;
        }

        if (!isset($query['include'])) {
            $query['include'] = json_encode(['id','createdAt','updatedAt','conferenceId', ['creatorId' => ['id', 'username']],'date',"topic"]);
        }
        
        $query['order'] = "-date";

        $query = http_build_query($query);

        $client = new Client();
        try {
            $r = $client->get($backend->url . 'objects/Meetings' . ($query ? '?'. $query : ''), ['headers' => [
                'X-Appercode-Session-Token' => $backend->token
            ]]);
        } catch (RequestException $e) {
            dd($e->getMessage());
            throw new \Exception('Error while getting meetings list');
        };

        $data = static::decode($r->getBody()->getContents());

        $result = new Collection();

        foreach ($data as $datum) {
            $result->push(new static($datum, $backend));
        }

        return $result;
    }

    public static function get(Backend $backend, string $meetingId, bool $withParticipants = false): Meeting
    {
        /**
         * @var Meeting $result
         */
        $result = null;
        $params['where'] = json_encode(['id' => $meetingId]);
        $queryResult = static::fetch($backend, $params);
        if ($queryResult->isNotEmpty()) {
            $result = $queryResult->first();
            $result->setBackend($backend);
            $shortView = static::getProfiles($backend, [$result->creatorId]);
            if ($shortView) {
                $result->creator['shortView'] = $shortView[$result->creatorId];
            }
            if ($withParticipants) {
                $result->loadParticipants();
            }
        }
        return $result;
    }

    public function save()
    {
        $backend = $this->backend;
        if (isset($backend->token)) {
            $client = new Client;
            try {
                if ($this->id) {
                    $r = $client->put($backend->url . 'objects/Meetings/' . $this->id, [
                        'headers' => ['X-Appercode-Session-Token' => $backend->token],
                        'json' => $this->toArrayForUpdate()
                    ]);
                }
                else{
                    $r = $client->post($backend->url . 'objects/Meetings', [
                        'headers' => ['X-Appercode-Session-Token' => $backend->token],
                        'json' => $this->toArrayForUpdate()
                    ]);
                }
            } catch (RequestException $e) {
                dd($e->getMessage());
                throw new \Exception('Meeting save error');
            };
        } else {
            throw new \Exception('No backend provided');
        }

        $data = static::decode($r->getBody()->getContents(), 1);
        //$meeting = new Meeting($data, $backend);

        return $this;
    }

    public function delete()
    {
        $client = new Client;

        $r = $client->delete($this->backend->url . 'objects/Meetings/' . $this->id, ['headers' => [
            'X-Appercode-Session-Token' => $this->backend->token
        ]]);

        return $this;
    }

    public static function notifyNewParticipants(Backend $backend, Meeting $meeting, $userIds)
    {
        static::createPush($backend, 'Invitation to the meeting', 'You are invited to the meeting "'.$meeting->topic.'", '. $meeting->date->format('Y-m-d') . ' at ' . $meeting->date->format('H:i') , $userIds);
    }

    public static function notifyMeetingCancellation(Backend $backend,Meeting $meeting)
    {
        $userIds = [];
        foreach ($meeting->participants as $item) {
            $userIds[] = $item->userId;
        }
        static::createPush($backend, 'Meeting cancellation', 'Meeting "'.$meeting->topic.'" has beed cancelled', $userIds);
    }

    public static function notifyCreatorChangeStatus(Backend $backend, $participantId, $status) {
        $participant = Participant::getById($backend, $participantId);
        $meeting = Meeting::get($backend, $participant->meetingId);
        $message = '';
        if ($status == Participant::STATUS_ACCEPTED) {
            $message = 'has accepted the invitation';
        }
        else {
            $message = 'has cancelled the invitation';
        }
        static::createPush($backend, 'Invitation changed status', $participant->userShortView() . ' ' . $message, [$meeting->creatorId]);
    }

    public static function notifyRemovedFromMeeting(Backend $backend, Participant $participant)
    {
        $meeting = Meeting::get($backend, $participant->meetingId);
        static::createPush($backend, 'Invitation cancellation', 'Your invitation to the meeting "'.$meeting->topic.'" has been cancelled', [$participant->userId]);
    }

    public static function createPush(Backend $backend, string $title, string $text, array $userIds)
    {
        $fields['title'] = $title;
        $fields['body'] = $text;
        $fields['to'] = $userIds;
        $push = Push::create($backend, $fields);
        return true;
    }
}