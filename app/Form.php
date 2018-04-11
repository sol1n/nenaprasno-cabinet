<?php

namespace App;

use App\Backend;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use App\Traits\Controllers\ModelActions;
use App\Traits\Models\AppercodeRequest;

class Form
{
    use ModelActions, AppercodeRequest;

    private $backend;

    const STATE_ACTIVE = 'active';
    const STATE_INACTIVE = 'inactive';

    protected function baseUrl(): String
    {
        return 'forms';
    }

    public function setBackend(Backend &$backend): Form
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
        $result = self::request([
            'method' => 'GET',
            'headers' => ['X-Appercode-Session-Token' => $backend->token],
            'url' => $backend->url . 'forms/' . $id,
        ]);

        return $result->getBody()->getContents();
    }

    public static function getFromRaw(string $data): Form
    {
        $decodedData = json_decode($data, 1);
        if (is_null($decodedData)) {
            throw new \Exception('Can`t parse json data');
        }
        return new self($decodedData);
    }

    private static function fetch(Backend $backend): array
    {
        $data = self::jsonRequest([
            'method' => 'GET',
            'headers' => ['X-Appercode-Session-Token' => $backend->token],
            'url' => $backend->url . 'forms?take=-1',
        ]);

        return $data;
    }

    public function save(): Form
    {
        if (isset($this->backend->token)) {
            $result = self::request([
                'method' => 'PUT',
                'json' => $this->toArray(),
                'headers' => ['X-Appercode-Session-Token' => $this->backend->token],
                'url' => $this->backend->url . 'forms/' . $this->id,
            ]);
        } else {
            throw new \Exception('No backend provided');
        }

        return self::getFromRaw($result->getBody()->getContents())->setBackend($this->backend);
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

    public static function getOwnResponses(Backend $backend, $id) 
    { 
        $decoded = self::jsonRequest([
            'method' => 'GET',
            'headers' => ['X-Appercode-Session-Token' => $backend->token],
            'url' => $backend->url . 'forms/' . $id . '/response/own',
        ]);

        if (isset($decoded[0])) { 
            $data = $decoded[0]['data']; 
            return collect($data); 
        }
 
        return null; 
    } 
}
