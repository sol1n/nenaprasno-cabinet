<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\User;
use App\Schema;
use App\Services\SchemaManager;
use App\Exceptions\Schema\SchemaSaveException;
use App\Exceptions\Schema\SchemaCreateException;
use App\Exceptions\Schema\SchemaDeleteException;
use App\Exceptions\Schema\SchemaNotFoundException;

class SchemasTest extends TestCase
{
    private $schemaManager;

    private function getTestSchemaData()
    {
        return [
            'name' => 'testsSchema',
            'title' => 'testsSchemaTitle',
            'isLogged' => false,
            'isDeferredDeletion' => true,
            'fields' => [
                [
                    'localized' => false,
                    'name' => 'testSchemaField',
                    'title' => 'testSchemaFieldName',
                    'type' => 'String',
                    'multiple' => false
                ]
            ],
            'viewData' => []
        ];
    }

    public function setUp()
    {
        parent::setUp();

        $user = User::Login([
            'login' => env('TEST_LOGIN'),
            'password' => env('TEST_PASSWORD')
        ], false);

        $this->withSession(['session-token' => $user->token()]);
        $this->schemaManager = app(SchemaManager::class);
    }

    public function test_fail_on_find_schema_with_wrong_name()
    {
        $name = 'NotExistingSchema';
        $this->expectException(SchemaNotFoundException::class);
        $this->schemaManager->find($name);
    }

    public function test_can_create_schema()
    {
        $data = $this->getTestSchemaData();
        $schema = $this->schemaManager->create($data);
        
        $this->assertInstanceOf(Schema::class, $schema);
        $this->assertEquals($data['name'], $schema->id);
        $this->assertTrue(count($data['fields']) == count($schema->fields));

        $this->schemaManager->delete($schema->id);
    }

    public function test_can_find_schema()
    {
        $data = $this->getTestSchemaData();
        $schema = $this->schemaManager->create($data);

        $wantedSchema = $this->schemaManager->find($data['name']);

        $this->assertEquals($wantedSchema->id, $schema->id);

        $this->schemaManager->delete($schema->id);
    }

    public function test_can_delete_schema()
    {
        $data = $this->getTestSchemaData();
        $schema = $this->schemaManager->create($data);
        $this->schemaManager->delete($schema->id);

        $this->expectException(SchemaNotFoundException::class);

        $this->schemaManager->find($data['name']);
    }

    public function test_can_update_schema()
    {
        $data = $this->getTestSchemaData();
        $schema = $this->schemaManager->create($data);

        $schema = $this->schemaManager->save($schema->id, ['title' => 'updatedTitle']);

        $this->assertEquals($schema->title, 'updatedTitle');

        $this->schemaManager->delete($schema->id);
    }

    public function test_can_add_and_remove_fields()
    {
        $data = $this->getTestSchemaData();
        $schema = $this->schemaManager->create($data);

        $schema = $this->schemaManager->save($schema->id, ['newFields' => [
            'testSchemaAddedField' => [
                'localized' => false,
                'name' => 'testSchemaAddedField',
                'title' => 'testSchemaAddedFieldName',
                'type' => 'String'
                ]
            ]
        ]);

        $this->assertEquals(count($schema->fields), 2);

        $schema = $this->schemaManager->save($schema->id, ['deletedFields' => [
            'testSchemaAddedField' => [
                'localized' => false,
                'name' => 'testSchemaAddedField',
                'title' => 'testSchemaAddedFieldName',
                'type' => 'String'
                ]
            ]
        ]);

        $this->assertEquals(count($schema->fields), 1);

        $this->schemaManager->delete($schema->id);
    }

}
