<?php
/**
 * Created by PhpStorm.
 * User: tsyrya
 * Date: 04/11/2017
 * Time: 09:59
 */

namespace App;


use App\Exceptions\Backend\TokenExpiredException;
use App\Services\UserManager;
use App\Traits\Models\AppercodeRequest;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Mail\Mailer;
use Illuminate\Mail\MailServiceProvider;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use League\Flysystem\Config;
use Symfony\Component\Finder\Iterator\PathFilterIterator;

class Meeting
{

    use AppercodeRequest;

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
        $this->conferenceId = isset($data['conferenceId']) ? $data['conferenceId'] : null;
        $this->date = isset($data['date']) ? Carbon::parse($data['date']) : null;
        $this->topic = isset($data['topic']) ? $data['topic'] : '';
        if (isset($data['participants']) and is_array($data['participants'])) {
            $this->participants = new Collection();
            foreach ($data['participants'] as $item) {
                $this->participants->push(new Participant($item, $backend));
            }
        }
        else{
            $this->participants = new Collection();
        }

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
            'topic' => $this->topic,
            'participants' => $this->participants->pluck('id')->toArray()
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

//    public function loadParticipants($language = 'en')
//    {
//        $this->participants = Participant::forMeeting($this->backend, $this->id, true, $language);
//    }

    public static function getCreatedMeetings(Backend $backend, $conferenceId, int $creatorId, bool $withProfiles =  false, $language = 'en'): Collection
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

        $userIds = [];
        foreach ($result as $item) {
            if ($item->participants) {
                foreach ($item->participants as $participant) {
                    if ($participant->userId) {
                        $userIds[] = $participant->userId;
                    }
                }
            }
        }

        if ($withProfiles and $userIds) {
            $shortViews = static::getProfiles($backend, $userIds, $language);
            foreach ($result as $item) {
                foreach ($item->participants as $participant) {
                    if (isset($shortViews[$participant->userId])) {
                        $participant->user['id'] = $participant->userId;
                        $participant->user = $shortViews[$participant->userId];
                    }
                }
            }
        }

        return $result;
    }
    public static function getInvitations(Backend $backend, $conferenceId, int $userId, bool $withProfiles =  false, $language = 'en'): Collection
    {
        $query = ['where' => json_encode(['userId' => $userId])];
        $participants = Participant::fetch($backend, $query, true, $language);
        if ($participants->isNotEmpty()) {
            $ids = $participants->pluck('id')->toArray();
            $where = [];
            if ($ids) {
                $conditions = [];
                foreach ($ids as $id) {
                    $conditions[] = ['participants' => ['$contains' => $id]];
                }
                $where = ['$or' => $conditions];
            }
            if ($conferenceId) {
                $where['conferenceId'] = $conferenceId;
            }
            if ($where) {
                $query = ['where' => json_encode($where)];
            }
            $meetings = static::fetch($backend, $query);
            $userIds = $meetings->pluck('creatorId')->toArray();
            if ($withProfiles and $userIds) {
                $shortViews = static::getProfiles($backend, $userIds, $language);
                foreach ($meetings as $item) {
                    if (isset($shortViews[$item->creatorId])) {
                        $item->creator['shortView'] = static::compileUserView($item->creatorId, $shortViews[$item->creatorId]);
                    }
                }
            }
            foreach ($meetings as $meeting) {
                foreach ($meeting->participants as $participant) {
                    $fp = $participants->where('id', $participant->id)->first();
                    if ($fp) {
                        $meeting->participants = collect([$fp]);
                        break;
                    }
                }

            }
            return $meetings;
        }
        return new Collection();
    }


    public static function getProfiles(Backend $backend, array $userIds, $language = 'en', $additionalData = false)
    {
        $shortViews = [];
        /**
         * @var Schema $profileSchema
         */
        $profileSchema = static::getUserProfileScheme($backend);
        if ($profileSchema) {
            $queryProfile['where'] = json_encode(['userId' => ['$in' => $userIds]]);
            $objects = Meeting::getProfilesObjects($profileSchema, $backend, $queryProfile, $language);
            $userField = $profileSchema->getUserLinkField();
            foreach ($objects as $object) {
                if ($object->fields[$userField['name']]) {
                    $userIds[] = $object->fields[$userField['name']];
                    $fields['shortView'] = static::userData($object);
                    if (isset($object->fields['company'])) {
                        $fields['company'] = $object->fields['company'];
                    }
                    else{
                        $fields['company'] = '';
                    }
                    if (isset($object->fields['position'])) {
                        $fields['position'] = $object->fields['position'];
                    }
                    else{
                        $fields['position'] = '';
                    }

                    if (isset($object->fields['photoFileId'])) {
                        $fields['photoFileId'] = $object->fields['photoFileId'];
                    }
                    else{
                        $fields['photoFileId'] = '';
                    }

                    if ($additionalData) {
                        if (isset($object->fields['email'])) {
                            $fields['email'] = $object->fields['email'];
                        }
                        else{
                            $fields['email'] = '';
                        }
                        $fields['language'] = $language;
                    }

                    $shortViews[$object->fields[$userField['name']]] =$fields;
                }
            }
        }

        return $shortViews;

    }

    public static function decode($data) {
        return json_decode($data, 1);
    }

    public static function regenerate(Backend &$backend) {
        $user = new User();
        $user = $user->regenerate($backend);
        $backend->token = $user->token();
        return $user;
    }

    private static function fetch(Backend $backend, array $params = []): Collection
    {
        $query = [];
        if ($params) {
            $query = $params;
        }

        if (!isset($query['include'])) {
            $query['include'] = json_encode(['id','createdAt','updatedAt','conferenceId', 'topic', 'date', ['creatorId' => ['id', 'username']],['participants' => ['id', 'status', 'userId',  'userInfo']]]);
        }

        $query['order'] = "-date";

        $query = http_build_query($query);

        $data = static::jsonRequest([
            'method' => 'GET',
            'headers' => ['X-Appercode-Session-Token' => $backend->token],
            'url' => $backend->url . 'objects/Meetings' . ($query ? '?'. $query : '')
        ]);

        $result = new Collection();

        foreach ($data as $datum) {
            if (isset($datum['participants']) and $datum['participants']) {
                $datum['participants'] = static::decode($datum['participants']);
            }
            $result->push(new static($datum, $backend));
        }

        return $result;
    }

    public static function get(Backend $backend, string $meetingId, bool $withParticipants = false, $language = 'en')
    {
        /**
         * @var Meeting $result
         */
        $result = null;//new Meetings();
        $params['where'] = json_encode(['id' => $meetingId]);
        $queryResult = static::fetch($backend, $params);

        if ($queryResult->isNotEmpty()) {
            $result = $queryResult->first();
            $result->setBackend($backend);
            $shortView = static::getProfiles($backend, [$result->creatorId], $language);
            if ($shortView) {
                $result->creator = static::compileUserView($result->creatorId,$shortView[$result->creatorId]);
            }
            $userIds = [];
            foreach ($result->participants as $item) {
                $userIds[] = $item->userId;
            }
            if ($withParticipants and $userIds) {
                $shortViews = static::getProfiles($backend, $userIds, $language);
                foreach ($result->participants as $participant) {
                    if (isset($shortViews[$participant->userId])) {
                        $participant->user = static::compileUserView($participant->userId, $shortViews[$participant->userId]);
                    }
                }
            }
        }
        return $result;
    }

    public static function compileUserView($userId, $shortView) {
        return [
            'id' => $userId,
            'shortView' => isset($shortView['shortView']) ?  $shortView['shortView'] : '',
            'company' => isset($shortView['company']) ? $shortView['company'] : '',
            'position' => isset($shortView['position']) ? $shortView['position'] : '',
            'photoFileId' => isset($shortView['photoFileId']) ? $shortView['photoFileId'] : ''
        ];
    }

    public function save()
    {
        $backend = $this->backend;
        $data = [];
        if (isset($backend->token)) {
            if ($this->id) {
                $data = static::jsonRequest([
                    'method' => 'PUT',
                    'headers' => ['X-Appercode-Session-Token' => $backend->token],
                    'json' => $this->toArrayForUpdate(),
                    'url' => $backend->url . 'objects/Meetings/' . $this->id
                ]);
            }
            else{
                $data = static::jsonRequest([
                    'method' => 'POST',
                    'headers' => ['X-Appercode-Session-Token' => $backend->token],
                    'json' => $this->toArrayForUpdate(),
                    'url' => $backend->url . 'objects/Meetings'
                ]);
            }
        } else {
            throw new \Exception('No backend provided');
        }

        if (isset($data['id'])) {
            $this->id = $data['id'];
        }

        return $this;
    }

    public function delete()
    {

        static::request([
            'method' => 'DELETE',
            'headers' => ['X-Appercode-Session-Token' => $this->backend->token],
            'url' => $this->backend->url . 'objects/Meetings/' . $this->id
        ]);

        return $this;
    }

    public static function notifyNewParticipants(Backend $backend, Meeting $meeting, $userIds, $link, $linkForButtons)
    {
        try {
            $userProfiles = Meeting::getUserProfilesAllLanguages($backend, $userIds);
            static::createPush($backend, 'Meetings', trans('meeting.invitation text', ['topic' => $meeting->topic, 'date' => $meeting->date->format('Y-m-d'), 'time' => $meeting->date->format('H:i')], "en"), $userIds, $link, "en");
            static::createPush($backend, 'Встречи', trans('meeting.invitation text', ['topic' => $meeting->topic, 'date' => $meeting->date->format('Y-m-d'), 'time' => $meeting->date->format('H:i')], "ru"), $userIds, $link, "ru");
            static::createMail(trans('meeting.invitation', [], "en"), 'invite', $meeting, $userProfiles, "en", $linkForButtons);

//            foreach ($userProfiles as $userProfile) {
//                $users = array_keys($userProfile);
//                static::createPush($backend, trans('meeting.invitation', [], $language), trans('meeting.invitation text', ['topic' => $meeting->topic, 'date' => $meeting->date->format('Y-m-d'), 'time' => $meeting->date->format('H:i')], $language), $users, $link);

//            }
        }
        catch( \Exception $e) {
            dd($e->getMessage());
        }
    }

    public static function notifyMeetingCancellation(Backend $backend,Meeting $meeting, $link = '')
    {
        $userIds = [];
        foreach ($meeting->participants as $item) {
            $userIds[] = $item->userId;
        }
        $userProfiles = Meeting::getUserProfilesAllLanguages($backend, $userIds);
        static::createPush($backend, 'Meetings', __('meeting.cancellation text', ['topic' => $meeting->topic], "en"), $userIds, $link, "en");
        static::createPush($backend, 'Встречи', __('meeting.cancellation text', ['topic' => $meeting->topic], "ru"), $userIds, $link, "ru");
        static::createMail(__('meeting.cancellation', [], "en"),'cancel', $meeting, $userProfiles, "en");
//        static::createMail(__('meeting.cancellation', [], "en"),'cancel', $meeting, $userProfiles, "en");
//        static::createPush($backend, 'Отмена встречи', 'Встреча "'.$meeting->topic.'" была отменена', $userIds);
//        static::createMail('Отмена встречи','cancel', $meeting, $userIds);
    }

    public static function notifyCreatorChangeStatus(Backend $backend, $participantId, $status, $link) {
        $participant = Participant::getById($backend, $participantId, true);
        $query = ['where' => json_encode(['participants' => ['$contains' => $participantId]])];
        $meetings = Meeting::fetch($backend, $query);
        if ($meetings->isNotEmpty()) {
            $meeting = $meetings->first();
            $creatorProfile = static::getUserProfilesAllLanguages($backend, [$meeting->creatorId], true);
            $participantProfile = static::getUserProfilesAllLanguages($backend, [$participant->userId], true);
            $message = '';
            if ($status == Participant::STATUS_ACCEPTED) {
                $messageEn = __('meeting.accept inv', [], "en");
                $messageRu = __('meeting.accept inv', [], "ru");
            } else {
                $messageEn = __('meeting.cancel inv', [], "en");
                $messageRu = __('meeting.cancel inv', [], "ru");
            }
            static::createPush($backend, 'Meetings', $participantProfile['shortView']  . ' ' . $messageEn . ' your invitation', [$meeting->creatorId], $link, "en");
            static::createPush($backend, 'Встречи', $participantProfile['shortViewRu']  . ' ' . $messageRu . ' ваше приглашение', [$meeting->creatorId], $link, "ru");
            static::createMailForStatus($meeting,$creatorProfile, $participantProfile, $status);
        }
    }

    public static function notifyRemovedFromMeeting(Backend $backend, Meeting $meeting, Participant $participant, $link)
    {
        $participantProfile = static::getUserProfilesAllLanguages($backend, [$participant->userId], true);
        static::createPush($backend, 'Meetings', __('meeting.cancel invitation text', ['topic' => $meeting->topic], "en"), [$participant->userId], $link, "en");
        static::createPush($backend, 'Встречи', __('meeting.cancel invitation text', ['topic' => $meeting->topic], "ru"), [$participant->userId], $link,"ru");
        static::createMail(__('meeting.cancel invitation title', [], "en"), 'removed', $meeting, [$participant->userId => $participantProfile], "en");
    }

    public static function notifyMeetingChanged(Backend $backend, $meetingId, $link)
    {
        $meeting = Meeting::get($backend, $meetingId);
        $userIds = [];
        foreach ($meeting->participants as $participant) {
            $userIds[] = $participant->userId;
        }
        if ($userIds) {
            $userProfiles = Meeting::getUserProfilesAllLanguages($backend, $userIds);
//            static::createPush($backend, trans('meeting.invitation',[],$language), trans('meeting.invitation text',['topic' => $meeting->topic, 'date' => $meeting->date->format('Y-m-d'), 'time' => $meeting->date->format('H:i')], $language), $users, $link);
//            static::createMail(trans('meeting.invitation',[],$language),'invite', $meeting, $userProfile, $language);
            static::createPush($backend, 'Meetings', __('meeting.meeting change text', ['topic' => $meeting->topic], "en"), $userIds, $link, 'en', $meeting);
            static::createPush($backend, 'Встречи', __('meeting.meeting change text', ['topic' => $meeting->topic], "ru"), $userIds, $link, 'ru', $meeting);
            static::createMail(__('meeting.meeting changed', [], "en"), 'meeting-changed', $meeting, $userProfiles, "en");
//            foreach ($userProfiles as $language => $userProfile) {
//                $users = array_keys($userProfile);
//
//
//            }
        }
    }

    /**
     * Creates, sends and deletes push for a meeting
     * @param Backend $backend
     * @param string $title
     * @param string $text
     * @param array $userIds
     * @param $link
     * @param $language
     * @return Push
     */
    public static function createPush(Backend $backend, string $title, string $text, array $userIds, $link, $language, $logInfo = false)
    {
        $fields['title'] = $title;
        $fields['body'] = $text;
        $fields['to'] = $userIds;
        $params = urlencode('{"url":"' . $link . '","title":"' . $title . '","kioskMode":true,"shareSessionInfo":true}');
        $fields['data']['link'] = 'actor:WebBrowserActor?params=' . $params;
        $fields["installationFilter"]["language"] = [$language];
        try {
            $push = Push::create($backend, $fields);
            Push::send($backend, $push->id);
            Push::delete($backend, $push->id);
        }
        catch (\Exception $e) {
            dd($e->getMessage());
        }
        return $push;
    }

    /**
     * Returns user profiles for en and ru languages
     * @param $backend
     * @param $userIds
     * @param $one
     * @return array
     */
    public static function getUserProfilesAllLanguages($backend, $userIds, $one = false){
        $result = [];
        $profiles = Meeting::getProfiles($backend, $userIds, "en", true);
        $profilesRu = Meeting::getProfiles($backend, $userIds, "ru", true);
        foreach ($profiles as $userId => $profile) {
            if (isset($profilesRu[$userId])) {
                $profiles[$userId]['shortViewRu'] = $profilesRu[$userId]['shortView'] ?  $profilesRu[$userId]['shortView'] : $profile['shortView'];
                $profiles[$userId]['companyRu'] = $profilesRu[$userId]['company'];
                $profiles[$userId]['positionRu'] = $profilesRu[$userId]['position'];
                if ($profilesRu[$userId]['email']) {
                    $profiles[$userId]['email'] = $profilesRu[$userId]['email'];
                }
                if (!$profile['shortView'] and $profilesRu[$userId]['shortView']) {
                    $profile['shortView'] = $profilesRu[$userId]['shortView'];
                }
            }
        }
        if ($one) {
            $result = array_pop($profiles);
        }
        else {
            $result = $profiles;
        }

        return $result;
    }


    /**
     * Returns user profiles grouped by profile languages
     * @param $backend
     * @param $userIds
     * @return array
     */
    public static function getUserProfiles($backend, $userIds)
    {
        $languages = app(Settings::class)->getLanguages();
        $mainLanguage = 'en';
        if ($languages->count() > 0) {
            $mainLanguage = $languages->first();
        }
        $users = User::list($backend, ['where' => json_encode(['id' => ['$in' => $userIds]])]);
        $userByLanguages = [];
        foreach ($users as $user) {
            if (isset($user->language['short']) and $user->language['short'] == $mainLanguage or !$user->language) {
                $userByLanguages[$mainLanguage][] = $user;
            }
            else{
                $userByLanguages[$user->language][] = $user;
            }
        }
        $result = [];
        foreach ($userByLanguages as $language => $users) {
            $users = collect($users);
            $ids = $users->pluck('id')->toArray();
            $profiles = static::getProfiles($backend, $ids, $language, true);
            $result[$language] = $profiles;
        }
        return $result;
    }

    public static function getUserProfile($backend, $userId, $language = '')
    {
        if (!$language) {
            $languages = app(Settings::class)->getLanguages();
            $mainLanguage = 'en';
            if ($languages->count() > 0) {
                $mainLanguage = $languages->first();
            }
            $user = User::get($userId, $backend);
            $language = isset($user->language['short']) ? $user->language['short'] : 'en';
        }

        $profiles = static::getProfiles($backend, [$userId], $language, true);

        $result = array_pop($profiles);

        $result['language'] = $language;

        return $result;
    }

    public static function createMail(string $title,string $viewName, Meeting $meeting, array $users, $language, $linkForButtons = '')
    {
        $creator = $meeting->creator;
        foreach ($users as $userId => $user) {
            if (isset($user['email']) and $user['email']) {
                $hash = sha1('2017'.$meeting->id.'2017');
                $basicUrl = env('BASIC_URL');
                try {
                    static::sendMail($viewName, [
                        'user' => $user,
                        'creatorShortView' => $creator['shortView'] ? $creator['shortView'] : $creator['username'],
                        'date' => $meeting->date->format('Y-m-d'),
                        'time' => $meeting->date->format('H:i'),
                        'topic' => $meeting->topic,
                        'title' => $title,
                        'meeting' => $meeting,
                        'userId' => $userId,
                        'hash' => $hash,
                        'basicUrl' => $basicUrl,
                        'language' => $language,
                        'linkForButtons' => $linkForButtons
                    ], $user['email'], $title);
                }
                catch (\Exception $e) {
                    dd($e->getMessage());
                }
            }
        }
    }

    public static function createMailForStatus(Meeting $meeting, $creator, $participant, $status)
    {
        if (isset($creator['email']) and $creator['email']) {
            $action =  __('meeting.accept inv', [], "en");
            $actionRu = __('meeting.accept inv', [], "ru");
            if ($status == Participant::STATUS_CANCELLED) {
                $action = __('meeting.cancel inv', [], "en");
                $actionRu = __('meeting.cancel inv', [], "ru");
            }
            static::sendMail('decision', [
                'creator' => $creator,
                'participant' => $participant,
                'date' => $meeting->date->format('Y-m-d'),
                'time' => $meeting->date->format('H:i'),
                'topic' => $meeting->topic,
                'action' => $action,
                'actionRu' => $actionRu,
                'title' => 'Participant changed status',
                'language' => $creator['language']
            ], $creator['email'], __('meeting.participant changed status', [], $creator['language']));
        }
    }

    public static function sendMail($view, $params, $to, $title) {
        try {
            $settings = app(Settings::class);

            if ($settings->emailSettings and isset($settings->emailSettings['server'])) {
                $mailSettings = $settings->emailSettings;

                \Illuminate\Support\Facades\Config::set('mail.driver', 'smtp');
                \Illuminate\Support\Facades\Config::set('mail.host', $mailSettings['server']);
                \Illuminate\Support\Facades\Config::set('mail.port', $mailSettings['port']);
                \Illuminate\Support\Facades\Config::set('mail.username', $mailSettings['username']);
                \Illuminate\Support\Facades\Config::set('mail.password', $mailSettings['password']);
                if ($mailSettings['ssl']) {
                    \Illuminate\Support\Facades\Config::set('mail.encryption', 'ssl');
                }
                (new MailServiceProvider(app()))->register();
                try {
                    Mail::send('emails.' . $view, $params,
                        function ($message) use ($to, $title, $mailSettings) {
                            $message->to($to);
                            $message->subject($title);
                            $message->from($mailSettings['username']);//$mailSettings['username']
                        });
                } catch (\Exception $e) {
                    dd($e->getMessage());
                }
            }
        }
        catch (\Exception $e) {
            dd($e->getMessage());
        }
    }


    public static function getUserProfileScheme(Backend $backend): Schema
    {
        $json = static::jsonRequest([
            'method' => 'GET',
            'headers' => ['X-Appercode-Session-Token' => $backend->token],
            'url' => $backend->url  . 'schemas/UserProfiles'
        ]);

        return Schema::build($json);
    }

    public static function getUser(Backend $backend, int $userId, $language = 'en')
    {

        $query = http_build_query(['where' => json_encode(['userId' => $userId])]);

        $url = $backend->url . 'objects/UserProfiles?' . $query ;

        $json = static::jsonRequest([
            'method' => 'GET',
            'headers' => [
                'X-Appercode-Session-Token' => $backend->token,
                'X-Appercode-Language' =>$language
            ],
            'url' => $url
        ]);

        $profileSchema = static::getUserProfileScheme($backend);

        $result = [];
        if ($json) {

            $object = Object::build($profileSchema, $json[0]);
            $result = [
                'id' => $userId,
                'shortView' => static::userData($object),
                'company' => isset($object->fields['company']) ? $object->fields['company'] : '',
                'position' => isset($object->fields['position']) ? $object->fields['position'] : '',
                'photoFileId' => isset($object->fields['photoFileId']) ? $object->fields['photoFileId'] : ''
            ];
        }
        return $result;
    }

    public static function userData($object): String
    {
        $result = '';
        $data = [];
        if (isset($object->fields['lastName']) and $object->fields['lastName']) {
            $data[] = $object->fields['lastName'];
        }
        if (isset($object->fields['firstName']) and $object->fields['firstName']) {
            $data[] = $object->fields['firstName'];
        }
        $result = join(' ', $data);
        return $result;
    }


    public static function getProfilesObjects(Schema $schema, Backend $backend, $query = null, $language): Collection
    {
        $list = new Collection;

        if ($query) {
            $query = http_build_query($query);
        }
        else {
            $query = http_build_query(['take' => 200]);
        }

        $headers = [
            'X-Appercode-Session-Token' => $backend->token
        ];

        if ($language) {
            $headers['X-Appercode-Language'] = $language;
        }

        $client = new Client;
        $url = $backend->url . 'objects/' . $schema->id . ($query ? '?' . $query : '');

        $json = static::jsonRequest([
            'method' => 'GET',
            'headers' => $headers,
            'url' => $url
        ]);

        foreach ($json as $item) {
            $list->push(Object::build($schema, $item));
        }

        return $list;
    }
}