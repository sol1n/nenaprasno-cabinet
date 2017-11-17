<?php
/**
 * Created by PhpStorm.
 * User: tsyrya
 * Date: 10/10/2017
 * Time: 20:12
 */

namespace App;


use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Collection;

class Push
{

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

    public function __construct(array $data, Backend $backend = null)
    {
        $this->id = isset($data['id']) ? (int) $data['id'] : '';
        $this->body = isset($data['body']) ? $data['body'] : '';
        $this->title = isset($data['title']) ? $data['title'] : '';
        $this->to = isset($data['to']) ? $data['to'] : null;
        $this->data = isset($data['data']) ? $data['data'] : [];
        $this->isDeleted = isset($data['isDeleted']) ? (bool)$data['isDeleted'] : [];
        $this->createdAt = isset($data['created_at']) ? Carbon::parse($data['created_at']) : null;
        $this->updatedAt = isset($data['updatedAt']) ? Carbon::parse($data['updatedAt']) : null;
        if ($backend) {
            $this->setBackend($backend);
        }
        return $this;
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
            'data' => $this->data
        ];
    }

    public function toArrayForUpdate() {
        return [
            'body' => (string) $this->body,
            'title' => (string) $this->title,
            'to' => $this->to,
            'data' => $this->data
        ];
    }

    private function setBackend(Backend &$backend): Push
    {
        $this->backend = $backend;
        return $this;
    }

    private static function fetch(Backend $backend, array $params = []): array
    {
        if (isset($params['page'])) {
            $params['take'] = config('objects.objects_per_page');
            $params['skip'] = ($params['page'] - 1) * config('objects.objects_per_page');
        }

        $query = http_build_query($params);
        $client = new Client();
        try {
            $r = $client->get($backend->url . 'push?' . $query, ['headers' => [
                'X-Appercode-Session-Token' => $backend->token
            ]]);
        } catch (RequestException $e) {
            throw new \Exception('Error while getting pushes list');
        };

        $data = static::decode($r->getBody()->getContents());

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
        $client = new Client;
        try {
            $r = $client->get($backend->url . 'push/' . $id, ['headers' => [
                'X-Appercode-Session-Token' => $backend->token
            ]]);
        } catch (RequestException $e) {
            throw new \Exception('Error while getting push, id: ' . $id);
        };

        $data = static::decode($r->getBody()->getContents(), 1);

        return new Push($data);
    }

    public function save(): Push
    {
        $backend = $this->backend;
        if (isset($backend->token)) {
            $client = new Client;
            try {
                $r = $client->put($backend->url . 'push/' . $this->id, [
                    'headers' => ['X-Appercode-Session-Token' => $backend->token],
                    'json' => $this->toArrayForUpdate()
                ]);
            } catch (RequestException $e) {
                dd($this->toArray());
                throw new \Exception('Update push error');
            };
        } else {
            throw new \Exception('No backend provided');
        }

        $data = static::decode($r->getBody()->getContents(), 1);
        $push = new Push($data);
        $push->setBackend($backend);

        return $push;
    }

    public static function create(Backend $backend, array $fields): Push
    {
        $client = new Client;
        if (!isset($fields['to']) or !$fields['to']) {
            $fields['to'] = null;
        }
        try {
            $r = $client->post($backend->url . 'push', ['headers' => [
                'X-Appercode-Session-Token' => $backend->token
            ], 'json' => $fields]);
        } catch (ServerException $e) {
            throw new \Exception('Push create error');
        }

        $data = static::decode($r->getBody()->getContents(), 1);

        $push = new Push($data);

        $push->setBackend($backend);

        return $push;
    }



    public static function count(Backend $backend, $query = []): int {
        $result = 0;
        $client = new Client;

        $searchQuery = [];

        if (isset($query['search'])) {
            $searchQuery = ['where' => json_encode($query['search'])];
        }

        $query = http_build_query(array_merge(['take' => 0, 'count' => 'true'], $searchQuery));

        $url = $backend->url . 'push?' . $query;
        $r = $client->get($url, ['headers' => [
            'X-Appercode-Session-Token' => $backend->token
        ]]);

        if ($r->getHeader('x-appercode-totalitems')){
            $result = $r->getHeader('x-appercode-totalitems')[0];
        }

        return $result;
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
        $r = $client->get($url, ['headers' => [
            'X-Appercode-Session-Token' => $backend->token
        ]]);

        if ($r->getStatusCode() != 200) {
            $result = false;
        }

        return $result;
    }

    public static function delete(Backend $backend, $id)
    {
        $result = true;
        $client = new Client;

        $url = $backend->url . 'push/' . $id;
        $r = $client->delete($url, ['headers' => [
            'X-Appercode-Session-Token' => $backend->token
        ]]);

        if ($r->getStatusCode() != 200) {
            $result = false;
        }

        return $result;
    }

    public static function status(Backend $backend, $id)
    {
        $result = true;
        $client = new Client;

        $url = $backend->url . 'push/' . $id .'/status';
        $r = $client->get($url, ['headers' => [
            'X-Appercode-Session-Token' => $backend->token
        ]]);

        $data = static::decode($r->getBody()->getContents(), 1);

        return $data;
    }


}