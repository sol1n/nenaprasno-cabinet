<?php

namespace App\Services;


use App\Backend;
use App\File;
use App\Helpers\Breadcrumb;
use App\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\HttpFoundation\File\UploadedFile as SymfonyUploadedFile;

class FileManager
{
    CONST CACHE_ID = 'files';
    const CACHE_LIFETIME = 60;

    private $backend;

    public function __construct()
    {
        $this->backend = app(Backend::Class);
    }

    public function fetchCollection(){
        $files = $this->getCollectionFromCache();
        if (is_null($files)) {
            $files = File::tree($this->backend);
            $this->saveCollectionToCache($files);
        }
        return $files;
    }

    /**
     * Returns list of files
     * @return Collection|null
     */
    public function all()
    {
        $files = $this->fetchCollection();
        return $files;
    }

    /**
     * Returns folder by route
     * Result array has three keys:
     * 1. breadcrumbs - array, each item contains link and name for constructing breadcrumbs
     * 2. folder - needed folder
     * 3. children - folder children collection
     * 4. baselink - base link for this folder
     * @param array $route
     * @return array
     */
    public function getFolder(Array $route) : Array
    {
        $result = ['breadcrumbs' => [], 'folder' => [], 'baselink' => ''];
        $fileTree = $this->fetchCollection();
        $currentFolder = [];
        $links = File::getbaseLink();
        $root = $fileTree->get(File::ROOT_PARENT_ID);
        $result['breadcrumbs'][] = new Breadcrumb($links, $root->name);
        $compile = function() use(&$result, &$links, &$currentFolder) {
            $links .= $currentFolder->id . '/';
            $result['breadcrumbs'][] = new Breadcrumb($currentFolder->link, $currentFolder->name ?? $currentFolder->id);
        };
        $currentFolder = $fileTree->get(File::ROOT_PARENT_ID);
        foreach ($route as $r){
            if (in_array($r, $currentFolder->children)){
                $currentFolder = $fileTree->get($r);
                $compile();
            }
            else{
                Throw new RouteNotFoundException('Route ' . join('/',$route) . ' doesn\'t exist');
            }
        }
        $children = new Collection();
        if ($currentFolder->children) {
            foreach ($currentFolder->children as $child) {
                $children->push($fileTree->get($child));
            }
        }

        $result['baselink'] = $currentFolder->link;
        $result['folder'] = $currentFolder;
        $result['children'] = $children;
        return $result;
    }

    /**
     * Returns one File
     * @param $id
     * @return mixed|null
     */
    public function one($id)
    {
        $result = null;
        $files = $this->all();
        $result = $files->get($id);
        return $result;
    }

    private function getCacheTag(): String
    {
        $backend = app(Backend::Class);
        $userId = request()->session()->get($backend->code.'-id');
        return $backend->code . '-' . static::CACHE_ID . '-' . $userId;
    }


    public function saveCollectionToCache($data)
    {
        $cacheId = $this->getCacheTag();
        Cache::forget($cacheId);
        Cache::put($cacheId, $data, self::CACHE_LIFETIME);
    }

    private function getCollectionFromCache()
    {
        $cacheId = $this->getCacheTag();

        if (Cache::has($cacheId)) {
            return Cache::get($cacheId);
        } else {
            return null;
        }
    }

    public function search($query, $folder)
    {
        $result = new Collection();
        $files = $this->all();
        $result = $this->doSearch($files, $query, $folder);
        return $result;
    }

    private function extractBaseFields($file){
        $newFile = $file;
        $newFile->children = [];
        return $newFile;
    }

    /**
     * @param Collection $data
     * @param $query
     * @return Collection
     */
    private function doSearch($data, $query, $folder) {
        $result = new Collection();

        $dfs = function($data, $file, $query, $folder) use(&$dfs) {
            $result = [];
            /**
             * @var File $file
             */
            if (str_contains($file->name, $query) or str_contains($file->id, $query) and $file->id != $folder){
                $result[] = $this->extractBaseFields($file);
            }
            
            foreach ($file->children as $child) {
               $result = array_merge($result, $dfs($data, $data->get($child),$query, $folder));
            }
            return $result;
        };

        $result = collect($dfs($data, $data->get($folder), $query,$folder));

        return $result;
    }

    public function putToTree($tree, File $item){
        $tree->put($item->id, $item);
        $element = $tree->get($item->parentId);
        $element->children[] = $item->id;
        $tree->put($item->parentId, $element);
        return $tree;
    }

    /**
     * Add folder
     * @param $fields
     * @return File
     */
    public function addFolder($fields) : File {
        if (!$fields['parentId']){
            $fields['parentId'] = File::ROOT_PARENT_ID;
        }
        $folder = File::addFolder($fields, $this->backend);

        $files = $this->all();

        $files = $this->putToTree($files, $folder);

        $this->saveCollectionToCache($files);

        return $folder;
    }

    public function createFile($fileProperties, $parentId){
        $result = ['file' => null, 'error' => ''];
        $props = [
            'parentId' => $parentId,
            'name' => $fileProperties['name'],
            'shareStatus' => isset($fileProperties['shareStatus']) ? $fileProperties['shareStatus'] : File::STATUS_LOCAL,
            'isFile' => true,
            'fileType' =>  'file',
            'length' => $fileProperties['size'],
        ];
        $file = File::createFile($props, $this->backend);
        $file->length = $fileProperties['size'];
        if ($file){
            $result['file'] = $file;

            $files = $this->all();
            $files = $this->putToTree($files, $file);
            $this->saveCollectionToCache($files);
        }
        else{
            $result['error'] = 'File wasn\'t created';
        }
        return $result;
    }

    public function uploadFile(String $fileId, SymfonyUploadedFile $file){

        $multipart = [
                [
                    'name'     => 'file',
                    'filename' => $file->getClientOriginalName(),
                    'contents' => file_get_contents($file->getPathname()),
                ]
        ];

        $response = File::uploadFile($fileId, $multipart, $this->backend);
        return $response;
    }

    private function updateItem($tree, $item) {
        $tree->put($item->id, $item);
        return $tree;
    }

    public function deleteFile($fileId)
    {
        $props = [
            'markupAsDeleted' => true
        ];
        $response = File::delete($fileId, $props, $this->backend);
        if ($response) {
            $files = $this->all();
            $file = $files->get($fileId);
            $file->isDeleted = true;
            $files = $this->updateItem($files, $file);
            $this->saveCollectionToCache($files);
            return true;
        }
        else{
            return false;
        }
    }

    public function restoreFile($fileId)
    {
        $props = [
            'isDeleted' => false
        ];
        $response = File::update($fileId, $props, $this->backend);
        if ($response) {
            $files = $this->all();
            $file = $files->get($fileId);
            $file->isDeleted = false;
            $files = $this->updateItem($files, $file);
            $this->saveCollectionToCache($files);
            return true;
        }
        else{
            return false;
        }
    }

    public function getFolders($isMapped = false)
    {
        $result = new Collection();
        $files = $this->all();
        $result = $files->where('fileType', 'directory');
        if ($isMapped) {
            $result = $result->mapWithKeys(function ($item)  {
                return [$item->id => $item->name];
            });
        }
        return $result;
    }

    public function getPath($id) {
        $result = [];
        $files = $this->all();
        $currentFolder = $files->get($id);
        while ($currentFolder) {
            $breadcrumb = new Breadcrumb($currentFolder->link, $currentFolder->name);
            array_unshift($result, $breadcrumb);
            $currentFolder = $files->get($currentFolder->parentId);
        }
        return $result;
    }

    private function updateRights(&$files, $id, $rights, $deletingRights) {
        /**
         * @var File $file
         */
        $file = $files->get($id);
        $fileRights = $file->rights['total'];
        foreach ($rights as $rightName => $rightValue){
            if (!isset($fileRights[$rightName])) {
                $fileRights[$rightName] = $rightValue;
            }
        }
        foreach ($fileRights as $fileRightName => $fileRightValue){
            if (in_array($fileRightName, $deletingRights)){
                unset($fileRights[$fileRightName]);
            }
        }
        $file->rights['total'] = $fileRights;
        $files->put($id, $file);
        foreach ($file->children as $child){
            $this->updateRights($files, $child, $rights, $deletingRights);
        }
    }

    public function update($fileId, $fields, UploadedFile $uploadFile = null) {
        $files = $this->all();
        $file = $files->get($fileId);
        $isSuccess = true;
        if ($uploadFile) {
            $fields['length'] = $uploadFile->getSize();
        }
        $result = File::update($fileId, $fields, $this->backend);
        if ($result) {
            if ($uploadFile) {
                $isSuccess = $this->uploadFile($fileId, $uploadFile);
            }
        }
        else{
            $isSuccess = false;
        }
        if ($isSuccess){
            if ($result->parentId == $file->parentId) {
                $result->link = $file->link;
            }
            else {
                $oldParent = $files->get($file->parentId);
                $newParent = $files->get($result->parentId);
                if(($key = array_search($result->id, $oldParent->children)) !== false) {
                    unset($oldParent->children[$key]);
                }
                $newParent->children[] = $result->id;
                if ($result->fileType == 'directory') {
                    $result->link = $newParent->link . '/' . $result->id;
                }
                $files->put($oldParent->id, $oldParent);
                $files->put($newParent->id, $newParent);
            }
            $result->children = $file->children;
            $oldRights = $file->rights['adds'];
            $files->put($result->id, $result);

            $rights = $result->rights['adds'];

            $deletingRights = [];
            if (is_array($oldRights) and $oldRights) {
                foreach ($oldRights as $oldRightname => $oldRightValue) {
                    if (!isset($rights[$oldRightname])) {
                        $deletingRights[] = $oldRightname;
                    }
                }
            }

            foreach ($result->children as $child) {
                $this->updateRights($files, $child, $rights, $deletingRights);
            }


            $this->saveCollectionToCache($files);

            return true;
        }
        return false;
    }


    public function getFile($id)
    {
        /**
         * @var File $file
         */
        $file = app(FileManager::class)->one($id);
        if ($file->length > 0) {
            return ['fileResult' => $file->getFile($this->backend), 'file' => $file];
        }
        return ['fileResult' => null, 'file' => $file];
    }

    public function resize($id)
    {
        $response = File::resize($this->backend, $id);
        return $response? true : false;
    }

    public function count() {
        return $this->all()->count();
    }

    public function getLocale() {
        $locale = __('file');
        $locale = array_merge($locale, [
            'edit' => __('common.edit'),
            'delete' => __('common.delete'),
            'actions' => __('common.actions'),
            'created at' => __('common.created at'),
            'restore' => __('common.restore'),
            'search' => __('common.search'),
        ]);
        return $locale;
    }
}