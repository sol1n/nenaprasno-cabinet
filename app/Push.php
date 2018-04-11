<?php
/**
 * Created by PhpStorm.
 * User: tsyrya
 * Date: 10/10/2017
 * Time: 20:12
 */

namespace App;


use App\Traits\Models\AppercodeRequest;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Collection;
use App\Traits\Models\TokenExpired;

class Push
{
    use AppercodeRequest;

    /**
     * @var int
     */
    public $id;
    /**
     * @var string
     */
    public $body;
    /**
     * @var string
     */
    public $title;
    /**
     * @var array
     */
    public $to;
    /**
     * @var array
     */
    public $data;
    /**
     * @var string
     */
    public $status;
    /**
     * @var array
     */
    public $metadata;
    /**
     * @var array
     */
    public $installationFilter;
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

    const STATUS_MANUAL = 'manual';
    const STATUS_DELAYED = 'delayed';
    const STATUS_DONE = 'done';

    /**
     * Interval of auto sending
     */
    CONST INTERVAL = 5;

    CONST METADATA_DELAYED_DATETIME = 'delayedDateTime';

    CONST DATETIME_FORMAT = 'Y-m-d H:i';

    CONST TIMEZONE = 'Europe/Moscow';

    public function __construct(array $data, Backend $backend = null)
    {
        $this->id = isset($data['id']) ? (int) $data['id'] : '';
        $this->body = isset($data['body']) ? $data['body'] : '';
        $this->title = isset($data['title']) ? $data['title'] : '';
        $this->to = isset($data['to']) ? $data['to'] : null;
        $this->data = isset($data['data']) ? $data['data'] : [];
        $this->status = isset($data['status']) ? $data['status'] : '';
        $this->installationFilter = isset($data['installationFilter']) ? $data['installationFilter'] : [];
        $this->installationFilter = static::parseInstallationFilter($this->installationFilter);

        if (isset($data['metadata'])) {
            if (is_array($data['metadata'])) {
                $this->metadata = $data['metadata'];
            }
            else{
                $this->metadata = json_decode($data['metadata'],1);
            }
        }
        else{
            $this->metadata = [];
        }

        $this->isDeleted = isset($data['isDeleted']) ? (bool)$data['isDeleted'] : [];
        $this->createdAt = isset($data['created_at']) ? Carbon::parse($data['created_at']) : null;
        $this->updatedAt = isset($data['updatedAt']) ? Carbon::parse($data['updatedAt']) : null;
        if ($backend) {
            $this->setBackend($backend);
        }
        return $this;
    }

    public static function parseInstallationFilter($installationFilter = [])
    {
        foreach ($installationFilter as $index => $item) {
            if (is_string($item)) {
                $installationFilter[$index] = $item ? explode(',', $item) : '';
                foreach ($installationFilter[$index] as $key => $value) {
                    $installationFilter[$index][$key] = trim($value);
                }
            }
        }
        return $installationFilter;
    }

    public function toArray(): array
    {
        return [
            'id' => (string) $this->id,
            'createdAt' => $this->createdAt ? (string) $this->createdAt->toAtomString() : '',
            'updatedAt' => $this->updatedAt ? (string) $this->updatedAt->toAtomString() : '',
            'body' => (string) $this->body,
            'title' => (string) $this->title,
            'to' => $this->to,
            'isDeleted' => (bool)$this->isDeleted,
            'data' => $this->data,
            'status' => (string)$this->status,
            'metadata' => $this->metadata,
            'installationFilter' => static::isSetFilter($this->installationFilter) ? $this->installationFilter : []
        ];
    }

    public function toArrayForUpdate() {
        return [
            'body' => (string) $this->body,
            'title' => (string) $this->title,
            'to' => $this->to,
            'data' => $this->data,
            'status' => (string)$this->status,
            'metadata' => $this->metadata,
            'installationFilter' => static::isSetFilter($this->installationFilter) ? $this->installationFilter : new \stdClass()
        ];
    }

    private function setBackend(Backend &$backend): Push
    {
        $this->backend = $backend;
        return $this;
    }

    /**
     * Checks if installationFilter has at least one filled field
     * @return bool
     */
    public static function isSetFilter($installationFilter): bool {
        $result = false;
        if ($installationFilter and is_array($installationFilter)) {
            foreach ($installationFilter as $index => $value) {
                if ($value) {
                    $result = true;
                    break;
                }
            }
        }
        return $result;
    }

    private static function fetch(Backend $backend, array $params = []): array
    {
        if (isset($params['page'])) {
            $params['take'] = config('objects.objects_per_page');
            $params['skip'] = ($params['page'] - 1) * config('objects.objects_per_page');
        }

        $query = http_build_query($params);

        $data = self::jsonRequest([
            'method' => 'GET',
            'headers' => ['X-Appercode-Session-Token' => $backend->token],
            'url' => $backend->url . 'push?' . $query,
        ]);

        return $data;
    }

    public static function list(Backend $backend, array $params = []): Collection
    {
        $result = new Collection;

        $list = static::fetch($backend, $params);

        foreach ($list as $item) {
            $push = new Push($item);
            $push->setBackend($backend);
            $result->push($push);
        }

        return $result;
    }

    public static function decode($data) {
        return json_decode($data, 1);
    }

    public static function get(Backend $backend, int $id): Push
    {
        $data = self::jsonRequest([
            'method' => 'GET',
            'headers' => ['X-Appercode-Session-Token' => $backend->token],
            'url' => $backend->url . 'push/' . $id,
        ]);

        return new Push($data);
    }

    public function save($fields = []): Push
    {
        $backend = $this->backend;

        if (isset($backend->token)) {
            $data = static::jsonRequest([
                'method' => 'PUT',
                'headers' => ['X-Appercode-Session-Token' => $backend->token],
                'json' => ($fields ? $fields : $this->toArrayForUpdate()),
                'url' => $backend->url . 'push/' . $this->id,
            ]);
        } else {
            throw new \Exception('No backend provided');
        }

        $push = new Push($data);
        $push->setBackend($backend);

        return $push;
    }



    public static function create(Backend $backend, array $fields): Push
    {
        if (!isset($fields['to']) or !$fields['to']) {
            $fields['to'] = null;
        }

        $data = static::jsonRequest([
            'method' => 'POST',
            'headers' => ['X-Appercode-Session-Token' => $backend->token],
            'json' => $fields,
            'url' => $backend->url . 'push',
        ]);

        $push = new Push($data);

        $push->setBackend($backend);

        return $push;
    }



    public static function count(Backend $backend, $query = []): int {
        $searchQuery = [];

        if (isset($query['search'])) {
            $searchQuery = ['where' => json_encode($query['search'])];
        }

        $query = http_build_query(array_merge(['take' => 0, 'count' => 'true'], $searchQuery));

        $url = $backend->url . 'push?' . $query;

        return self::countRequest([
            'method' => 'GET',
            'headers' => ['X-Appercode-Session-Token' => $backend->token],
            'url' => $url,
        ]);
    }

    public function editLink()
    {
        return route('pushEdit', ['backend' => $this->backend->code, 'id' => $this->id]);// '/' . $this->backend->code . '/pushes/' . $this->id;
    }

    public function deleteLink()
    {
        return route('pushDelete', ['backend' => $this->backend->code, 'id' => $this->id]);//'/' . $this->backend->code . '/pushes/' . $this->id . '/delete';
    }

    public function statusLink()
    {
        return route('pushStatus', ['backend' => $this->backend->code, 'id' => $this->id]);// '/' . $this->backend->code . '/pushes/' . $this->id . '/status';
    }


    public static function send(Backend $backend, $id)
    {
        $result = true;
        $client = new Client;

        $url = $backend->url . 'push/' . $id .'/send';
        $r = static::request([
           'method' => 'GET',
           'headers' => ['X-Appercode-Session-Token' => $backend->token],
           'url' => $url
        ]);

        if ($r->getStatusCode() != 200) {
            $result = false;
        }

        return $result;
    }

    public static function delete(Backend $backend, $id)
    {
        $result = true;

        $url = $backend->url . 'push/' . $id;

        $r = self::request([
            'method' => 'DELETE',
            'headers' => ['X-Appercode-Session-Token' => $backend->token],
            'url' => $url
        ]);

        if ($r->getStatusCode() != 200) {
            $result = false;
        }

        return $result;
    }

    public static function status(Backend $backend, $id)
    {
        $url = $backend->url . 'push/' . $id .'/status';

        $data = static::jsonRequest([
            'method' => 'GET',
            'headers' => ['X-Appercode-Session-Token' => $backend->token],
            'url' => $url
        ]);

        return $data;
    }

    /**
     * Return installation filter by key, result would be separated by commas
     * @param $key
     * @return string
     */
    public function getInstallationFilterStr($key) {
        $result = '';
        if (isset($this->installationFilter[$key]) and is_array($this->installationFilter[$key])) {
            $result = join(',', $this->installationFilter[$key]);
        }
        return $result;
    }

    public function getDelayedDateTimestamp()
    {
        $result = 0;
        $carbonDate  = $this->getDelayedDate();
        if ($carbonDate) {
            $result = strtotime($carbonDate->toDateTimeString());
        }
        return $result;
    }

    /**
     * Return Carbon or null of delayedDateTime
     */
    public function getDelayedDate()
    {
        $result = null;
        if (isset($this->metadata[static::METADATA_DELAYED_DATETIME])) {
            $result = Carbon::createFromFormat(static::DATETIME_FORMAT, $this->metadata[static::METADATA_DELAYED_DATETIME], static::TIMEZONE);
        }
        return $result;
    }

    public static function getDevices()
    {
        return [
            'Android',
            'iOS'
        ];
    }

}