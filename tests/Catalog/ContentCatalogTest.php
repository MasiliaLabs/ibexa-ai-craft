<?php

declare(strict_types=1);

namespace Masilia\IbexaAiCraft\Tests\Catalog;

use PHPUnit\Framework\TestCase;

final class ContentCatalogTest extends TestCase
{
    use ContentCatalogFactoryTrait;

    public function testGetContentTypeSchemaReturnsFieldDefinitions(): void
    {
        $catalog = $this->createContentCatalog([
            'Content' => [
                'page' => [
                    'name' => 'Page',
                    'fields' => [
                        'title' => ['type' => 'ibexa_string', 'required' => true],
                        'description' => 'ibexa_text',
                        'blocks' => 'ibexa_matrix',
                    ],
                ],
            ],
        ]);

        $schema = $catalog->getContentTypeSchema('page');

        self::assertNotNull($schema);
        self::assertSame('page', $schema['identifier']);
        self::assertSame('Page', $schema['name']);
        self::assertArrayHasKey('title', $schema['fields']);
        self::assertArrayHasKey('description', $schema['fields']);
        self::assertArrayHasKey('blocks', $schema['fields']);

        self::assertTrue($schema['fields']['title']['required']);
        self::assertFalse($schema['fields']['description']['required']);
    }

    public function testGetContentTypeSchemaReturnsNullForUnknownType(): void
    {
        $catalog = $this->createContentCatalog([
            'Content' => [
                'page' => [
                    'name' => 'Page',
                    'fields' => ['title' => 'ibexa_string'],
                ],
            ],
        ]);

        self::assertNull($catalog->getContentTypeSchema('unknown_type'));
    }

    public function testLoadsFromMultipleGroups(): void
    {
        $catalog = $this->createContentCatalog([
            'Content' => [
                'page' => [
                    'name' => 'Page',
                    'fields' => ['title' => ['type' => 'ibexa_string', 'required' => true]],
                ],
            ],
            'Media' => [
                'image' => [
                    'name' => 'Image',
                    'fields' => ['image' => ['type' => 'ibexa_image', 'required' => true]],
                ],
            ],
            'Taxonomies' => [
                'tag' => [
                    'name' => 'Tag',
                    'fields' => ['name' => ['type' => 'ibexa_string', 'required' => true]],
                ],
            ],
        ]);

        self::assertNotNull($catalog->getContentTypeSchema('page'));
        self::assertNotNull($catalog->getContentTypeSchema('image'));
        self::assertNotNull($catalog->getContentTypeSchema('tag'));
    }

    public function testRequiredFieldDetectionTrustsIsRequired(): void
    {
        $catalog = $this->createContentCatalog([
            'Content' => [
                'page' => [
                    'name' => 'Page',
                    'fields' => [
                        'title' => ['type' => 'ibexa_string', 'required' => true],
                        'subtitle' => ['type' => 'ibexa_string', 'required' => false],
                    ],
                ],
            ],
        ]);

        $schema = $catalog->getContentTypeSchema('page');

        self::assertTrue($schema['fields']['title']['required']);
        self::assertFalse($schema['fields']['subtitle']['required']);
    }

    public function testRequiredFieldDetectionForEzimageAlwaysRequired(): void
    {
        $catalog = $this->createContentCatalog([
            'Content' => [
                'page' => [
                    'name' => 'Page',
                    'fields' => [
                        'image' => ['type' => 'ibexa_image', 'required' => false],
                    ],
                ],
            ],
        ]);

        $schema = $catalog->getContentTypeSchema('page');

        self::assertTrue($schema['fields']['image']['required']);
    }

    public function testRequiredFieldDetectionForStringLengthValidator(): void
    {
        $catalog = $this->createContentCatalog([
            'Content' => [
                'page' => [
                    'name' => 'Page',
                    'fields' => [
                        'title' => [
                            'type' => 'ibexa_string',
                            'required' => false,
                            'validator' => ['StringLengthValidator' => ['minStringLength' => 1]],
                        ],
                    ],
                ],
            ],
        ]);

        $schema = $catalog->getContentTypeSchema('page');

        self::assertTrue($schema['fields']['title']['required']);
    }

    public function testSkipsMissingGroups(): void
    {
        $catalog = $this->createContentCatalog([
            'Content' => [
                'page' => [
                    'name' => 'Page',
                    'fields' => ['title' => 'ibexa_string'],
                ],
            ],
            // Non-existent groups are silently skipped
        ]);

        self::assertNotNull($catalog->getContentTypeSchema('page'));
    }

    public function testGetAvailableContentTypesReturnsAllTypes(): void
    {
        $catalog = $this->createContentCatalog([
            'Content' => [
                'page' => [
                    'name' => 'Page',
                    'fields' => ['title' => 'ibexa_string'],
                ],
                'article' => [
                    'name' => 'Article',
                    'fields' => ['title' => 'ibexa_string'],
                ],
            ],
        ]);

        $types = $catalog->getAvailableContentTypes();

        self::assertArrayHasKey('page', $types);
        self::assertArrayHasKey('article', $types);
        self::assertCount(2, $types);
    }

    public function testEzimageFieldIncludesFileSizeValidator(): void
    {
        $catalog = $this->createContentCatalog([
            'Content' => [
                'page' => [
                    'name' => 'Page',
                    'fields' => [
                        'hero_image' => [
                            'type' => 'ibexa_image',
                            'validator' => [
                                'FileSizeValidator' => ['maxFileSize' => 8],
                                'AlternativeTextValidator' => ['required' => true],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $schema = $catalog->getContentTypeSchema('page');

        self::assertNotNull($schema);
        self::assertSame(8, $schema['fields']['hero_image']['maxFileSize']);
        self::assertTrue($schema['fields']['hero_image']['altTextRequired']);
    }

    public function testEzimageFieldDescriptionPassthrough(): void
    {
        $catalog = $this->createContentCatalog([
            'Content' => [
                'page' => [
                    'name' => 'Page',
                    'fields' => [
                        'hero_image' => [
                            'type' => 'ibexa_image',
                            'description' => 'Banner 1920x600 px, JPEG or PNG, max 8 MB',
                        ],
                    ],
                ],
            ],
        ]);

        $schema = $catalog->getContentTypeSchema('page');

        self::assertNotNull($schema);
        self::assertSame('Banner 1920x600 px, JPEG or PNG, max 8 MB', $schema['fields']['hero_image']['description']);
    }

    public function testEzstringFieldIncludesLengthConstraints(): void
    {
        $catalog = $this->createContentCatalog([
            'Content' => [
                'page' => [
                    'name' => 'Page',
                    'fields' => [
                        'subtitle' => [
                            'type' => 'ibexa_string',
                            'validator' => [
                                'StringLengthValidator' => ['minStringLength' => 2, 'maxStringLength' => 120],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $schema = $catalog->getContentTypeSchema('page');

        self::assertNotNull($schema);
        self::assertSame(2, $schema['fields']['subtitle']['minLength']);
        self::assertSame(120, $schema['fields']['subtitle']['maxLength']);
    }

    public function testEzmatrixFieldIncludesRowLimits(): void
    {
        $catalog = $this->createContentCatalog([
            'Content' => [
                'page' => [
                    'name' => 'Page',
                    'fields' => [
                        'blocks' => [
                            'type' => 'ibexa_matrix',
                            'settings' => [
                                'columns' => [
                                    ['identifier' => 'title', 'name' => 'Title'],
                                ],
                            ],
                            'validator' => [
                                'MatrixValueValidator' => ['minimumRowCount' => 1, 'maximumRowCount' => 10],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $schema = $catalog->getContentTypeSchema('page');

        self::assertNotNull($schema);
        self::assertSame(1, $schema['fields']['blocks']['minRows']);
        self::assertSame(10, $schema['fields']['blocks']['maxRows']);
    }

    public function testRelationListFieldIncludesItemLimits(): void
    {
        $catalog = $this->createContentCatalog([
            'Content' => [
                'page' => [
                    'name' => 'Page',
                    'fields' => [
                        'related' => [
                            'type' => 'ibexa_object_relation_list',
                            'settings' => ['selectionContentTypes' => ['article']],
                            'validator' => [
                                'RelationValidator' => ['minimumRelationLimit' => 1],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $schema = $catalog->getContentTypeSchema('page');

        self::assertNotNull($schema);
        self::assertSame(1, $schema['fields']['related']['minItems']);
        self::assertArrayNotHasKey('maxItems', $schema['fields']['related']);
    }

    public function testTranslatableFlagExposedOnFields(): void
    {
        $catalog = $this->createContentCatalog([
            'Content' => [
                'page' => [
                    'name' => 'Page',
                    'fields' => [
                        'title' => [
                            'type' => 'ibexa_string',
                            'translatable' => true,
                        ],
                        'slug' => [
                            'type' => 'ibexa_string',
                            'translatable' => false,
                        ],
                    ],
                ],
            ],
        ]);

        $schema = $catalog->getContentTypeSchema('page');

        self::assertNotNull($schema);
        self::assertTrue($schema['fields']['title']['translatable']);
        self::assertFalse($schema['fields']['slug']['translatable']);
    }
}
