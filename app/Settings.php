<?php

namespace App;

use App\Language;
use App\Backend;
use App\Services\SchemaManager;
use Carbon\Carbon;
use Illuminate\Http\Request;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Cache;
use App\Exceptions\SettingsSaveException;
use App\Exceptions\SettingsGetException;
use Illuminate\Support\Collection;

class Settings
{
    public $title;
    public $languages;
    public $userProfiles;
    public $newUsersRole;
    public $emailSettings;
    public $fcmServerKey;
    public $version;
    public $systemLogs;
    public $properties;
    public $id;
    public $createdAt;
    public $updatedAt;
    public $isDeleted;

    protected $cacheLifetime = 10;

    private function getCacheTag(): String
    {
        return app(Backend::Class)->code . '-' . self::class;
    }

    public function __construct(Backend $backend = null)
    {
        if (! $data = $this->getFromCache()) {
            if (!$backend) $backend = app(Backend::Class);
            $data = $this->fetch($backend);
        }
        $this->build($data);
    }

    private function fetch(Backend $backend): array
    {
        $client = new Client;
        try {
            $r = $client->get($backend->url . 'settings', ['headers' => [
                'X-Appercode-Session-Token' => $backend->token
            ]]);
        } catch (RequestException $e) {
            throw new SettingsGetException;
        };

        $data = json_decode($r->getBody()->getContents(), 1);
        
        $this->saveToCache($data);

        return $data;
    }

    private function build(array $data): Settings
    {
        $this->title = $data['title'];
        $this->languages = $data['languages'];
        $this->userProfiles = $data['userProfiles'];
        $this->newUsersRole = $data['newUsersRole'];
        $this->emailSettings = $data['emailSettings'];
        $this->fcmServerKey = $data['fcmServerKey'];
        $this->version = $data['version'];
        $this->systemLogs = $data['systemLogs'];
        $this->properties = $data['properties'];
        $this->id = $data['id'];
        $this->createdAt = new Carbon($data['createdAt']);
        $this->updatedAt = new Carbon($data['updatedAt']);
        $this->isDeleted = $data['isDeleted'];

        return $this;
    }

    private function saveToCache($data)
    {
        if (env('APPERCODE_ENABLE_CACHING') == 1) {
            Cache::put($this->getCacheTag(), $data, $this->cacheLifetime);
        }
    }

    private function getFromCache()
    {
        if (Cache::has($this->getCacheTag()) && (env('APPERCODE_ENABLE_CACHING') == 1)) {
            return Cache::get($this->getCacheTag());
        } else {
            return null;
        }
    }

    public function save(array $data, Backend $backend): Settings
    {
        foreach ($data as $key => $value) {
            if ($this->{$key} != $value) {
                $this->{$key} = $value;
            }
        }

        $json = [];
        foreach ($this as $key => $value) {
            if ($key != 'token') {
                $json[$key] = $value;
            }
        }

        $client = new Client;
        try {
            $r = $client->put($backend->url . 'settings', ['headers' => [
                'X-Appercode-Session-Token' => $backend->token
            ], 'json' => $json]);
        } catch (RequestException $e) {
            throw new SettingsSaveException;
        };

        $data = $this->fetch($backend);

        $this->build($data);

        return $this;
    }

    public function getProfileSchemas()
    {
        if ($this->userProfiles)
        {
            $result = new Collection;
            foreach ($this->userProfiles as $raw) {
                $exploded = explode('.', $raw);
                $result->put($raw, app(SchemaManager::class)->find($exploded[0]));
            }
        }
        else
        {
            $result = null;
        }
        
        return $result;
    }

    /**
     * Return array fo profile linking fields
     * SchemaCode => FieldName
     * @return array|Collection
     */
    public function getProfileSchemasLinkField()
    {
        $result = [];
        if ($this->userProfiles)
        {
            $result = new Collection;
            foreach ($this->userProfiles as $raw) {
                $exploded = explode('.', $raw);
                //$result->put($raw, app(SchemaManager::class)->find($exploded[0]));
                $result[$exploded[0]] = $exploded[1];
            }
        }

        return $result;
    }

    public function getLanguages()
    {
        $allLanguages = Language::list();
        foreach ($allLanguages as $language => $slug)
        {
            if (! in_array($slug, $this->languages))
            {
                unset($allLanguages[$language]);
            }
        }
        return $allLanguages;
    }
}
