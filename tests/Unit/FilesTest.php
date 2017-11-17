<?php

namespace Tests\Unit;


use App\Backend;
use App\File;
use App\Services\FileManager;
use App\User;
use Tests\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile as SymfonyUploadedFile;

class FilesTest extends TestCase
{
    /**
     * @var FileManager
     */
    private $fileManager;

    /**
     * @var Backend
     */
    private $backend;

    public function setUp()
    {
        parent::setUp();

        $this->backend = new Backend();
        $this->app['request']->setLaravelSession($this->app['session']->driver('array'));
        $user = User::Login(app(Backend::class), [
            'login' => env('TEST_LOGIN'),
            'password' => env('TEST_PASSWORD')
        ], false);

        $this->backend->token = $user->token();
        \App::instance(Backend::class, $this->backend);

        $this->withSession(['session-token' => $user->token()]);
        $this->fileManager = app(FileManager::class);
        //dd($this->backend);
    }

    public function test_create_file() {
        $fileProperties = [
            'name' => 'test_file',
            'shareStatus' => 'local',
            'length' => 1000,
            'size' => 1000
        ];
        $parentId = File::ROOT_PARENT_ID;
        $file = $this->fileManager->createFile($fileProperties, $parentId);
        $this->assertFalse(empty($file['file']->id));
        $this->fileManager->deleteFile($file['file']->id);
    }


    public function test_can_delete_file()
    {

        $fileProperties = [
            'name' => 'test_file',
            'shareStatus' => 'local',
            'length' => 1000,
            'size' => 0
        ];
        $parentId = File::ROOT_PARENT_ID;
        $file = $this->fileManager->createFile($fileProperties, $parentId);
        $res = $this->fileManager->deleteFile($file['file']->id);
        $this->assertNotFalse($res);
    }

    public function test_can_create_folder()
    {
        $folder = $this->fileManager->addFolder([
            'name' => 'test_folder',
            'parentId' => File::ROOT_PARENT_ID,
            'path' => '/'
        ]);
        $this->assertFalse(empty($folder->id));
        $this->fileManager->deleteFile($folder->id);
    }

    public function test_can_update_file()
    {
        $fileProperties = [
            'name' => 'test_file',
            'shareStatus' => 'local',
            'length' => 1000,
            'size' => 0
        ];
        $parentId = File::ROOT_PARENT_ID;
        $file = $this->fileManager->createFile($fileProperties, $parentId);
        $fileId = $file['file']->id;
        $res = $this->fileManager->update($fileId, ['name' => 'new_name']);
        $this->assertNotFalse($res);
        $this->fileManager->deleteFile($fileId);
    }

    public function test_can_upload_file() {
        $filename = 'testfile.jpg';
        $symfonyUploadedFile = new SymfonyUploadedFile(
            base_path().'/tests/Files/'. $filename,
            $filename
        );

        $fileProperties = [
            'name' => $symfonyUploadedFile->getClientOriginalName(),
            'size' => $symfonyUploadedFile->getSize(),
            'fileProperties' => [
                "rights" => [
                    "read" => true,
                    "write" => true,
                    "delete" => true
                ],
            ],
            "shareStatus" => "shared"
        ];

        $result = $this->fileManager->createFile($fileProperties, File::ROOT_PARENT_ID);
        $uploadResult = $this->fileManager->uploadFile(
            $result['file']->id,
            $symfonyUploadedFile
        );
        $this->assertNotFalse($uploadResult);
        $this->fileManager->deleteFile($result['file']->id);
    }
}