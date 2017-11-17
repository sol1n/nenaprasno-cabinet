<?php

namespace App;

use App\Backend;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Collection;
use App\Traits\Controllers\ModelActions;
use App\Exceptions\Backend\TokenExpiredException;

class Form
{
    use ModelActions;

    private $backend;

    const STATE_ACTIVE = 'active';
    const STATE_INACTIVE = 'inactive';

    protected function baseUrl(): String
    {
        return 'forms';
    }

    private function setBackend(Backend &$backend): Form
    {
        $this->backend = $backend;
        return $this;
    }

    public function __construct(array $data)
    {
        $this->id = $data['id'];
        $this->createdAt = new Carbon($data['createdAt']);
        $this->updatedAt = new Carbon($data['updatedAt']);
        $this->isDeleted = $data['isDeleted'];
        $this->status = isset($data['status']) ? $data['status'] : '';
        $this->title = isset($data['title']) ? $data['title'] : '';
        $this->parts = isset($data['parts']) ? $data['parts'] : [];
        $this->resultPart = isset($data['resultPart']) ? $data['resultPart'] : [];
        return $this;
    }

    public function toJson(): string
    {
        return json_encode($this->toArray());
    }

    public function toArray(): array
    {
        return [
            'id' => (string) $this->id,
            'createdAt' => (string) $this->createdAt->toAtomString(),
            'updatedAt' => (string) $this->updatedAt->toAtomString(),
            'status' => (string) $this->status,
            'title' => (string) $this->title,
            'parts' => (array) $this->parts,
            'resultPart' => (array) $this->resultPart,
            'isDeleted' => (bool) $this->isDeleted
        ];
    }

    public static function getRaw(Backend $backend, $id): string
    {
        $client = new Client;
        try {
            $r = $client->get($backend->url . 'forms/' . $id, ['headers' => [
                'X-Appercode-Session-Token' => $backend->token
            ]]);
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                if ($e->getResponse()->getStatusCode() == 401) {
                    throw new TokenExpiredException;
                }
            }
            throw new \Exception('Error while getting form, id: ' . $id);
        };

        return $r->getBody()->getContents();
    }

    public static function getFromRaw(string $data): Form
    {
        return new self(json_decode($data, 1));
    }

    private static function fetch(Backend $backend): array
    {
        $query = http_build_query(['take' => -1]);
        $client = new Client;
        try {
            $r = $client->get($backend->url . 'forms?' . $query, ['headers' => [
                'X-Appercode-Session-Token' => $backend->token
            ]]);
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                if ($e->getResponse()->getStatusCode() == 401) {
                    throw new TokenExpiredException;
                }
            }
            throw new \Exception('Error while getting forms list');
        };

        $data = json_decode($r->getBody()->getContents(), 1);

        return $data;
    }

    public function save(): Form
    {
        $backend = $this->backend;
        if (isset($this->backend->token)) {
            $client = new Client;
            try {
                $r = $client->put($this->backend->url . 'forms/' . $this->id, [
                    'headers' => ['X-Appercode-Session-Token' => $this->backend->token],
                    'json' => $this->toArray()
                ]);
            } catch (RequestException $e) {
                throw new \Exception('Update form error');
            };
        } else {
            throw new \Exception('No backend provided');
        }

        return self::getFromRaw($r->getBody()->getContents())->setBackend($backend);
    }

    public static function get(String $id, Backend $backend): Form
    {
        return self::getFromRaw(self::getRaw($backend, $id))->setBackend($backend);
    }

    public static function list(Backend $backend): Collection
    {
        $result = new Collection;

        foreach (static::fetch($backend) as $raw) {
            $form = new Form($raw);
            $form->setBackend($backend);
            $result->push($form);
        }

        return $result;
    }

    public function parseQuestions()
    {
        $questions = [];
        foreach ($this->parts as $part) {
            if (isset($part['sections'])) {
                foreach ($part['sections'] as $section) {
                    if (isset($section['groups'])) {
                        foreach ($section['groups'] as $group) {
                            if (isset($group['controls'][0])) {
                                $options = [];
                                $question = $group['controls'][0];
                                if (isset($question['options']['value'])) {
                                    foreach ($question['options']['value'] as $option) {
                                        $options[$option['value']] =
                                            ['title' => $option['title'], 'value' => $option['value']];
                                    }
                                }
                                $questions[$question['id']] = ['title' => $question['title'], 'options' => $options];
                            }
                        }
                    }
                }
            }
        }
        $this->questions = collect($questions);
        return $this;
    }
}
