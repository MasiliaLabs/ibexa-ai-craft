<?php

declare(strict_types=1);

namespace Masilia\IbexaAiCraft\Tests\Catalog;

use Masilia\Bundle\IbexaAiCraft\Catalog\BlockCatalog;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\NullAdapter;

final class BlockCatalogTest extends TestCase
{
    use BlockCatalogFactoryTrait;

    public function testGetAvailableBlocksReturnsDetailedSchemas(): void
    {
        $catalog = $this->createBlockCatalog([
            'info_cards' => [
                'name' => 'Info Cards',
                'fields' => [
                    'cards' => [
                        'type' => 'ibexa_matrix',
                        'settings' => [
                            'columns' => [
                                ['identifier' => 'icon', 'name' => 'Icon'],
                                ['identifier' => 'title', 'name' => 'Title'],
                                ['identifier' => 'body', 'name' => 'Body'],
                            ],
                        ],
                    ],
                    'items' => [
                        'type' => 'ibexa_object_relation_list',
                        'settings' => ['selectionContentTypes' => ['card_item']],
                    ],
                    'heading' => 'ibexa_string',
                ],
            ],
        ]);

        $blocks = $catalog->getAvailableBlocks();

        self::assertArrayHasKey('info_cards', $blocks);
        self::assertSame('Info Cards', $blocks['info_cards']['name']);

        $fields = $blocks['info_cards']['fields'];

        self::assertSame(
            ['type' => 'ibexa_matrix', 'required' => false, 'translatable' => false, 'columns' => [
                ['identifier' => 'icon', 'name' => 'Icon'],
                ['identifier' => 'title', 'name' => 'Title'],
                ['identifier' => 'body', 'name' => 'Body'],
            ]],
            $fields['cards'],
        );

        self::assertSame(
            ['type' => 'ibexa_object_relation_list', 'required' => false, 'translatable' => false, 'allowedTypes' => ['card_item']],
            $fields['items'],
        );

        self::assertSame(['type' => 'ibexa_string', 'required' => false, 'translatable' => false], $fields['heading']);
    }

    public function testGetBlockSchemaReturnsNullForUnknownBlock(): void
    {
        $catalog = $this->makeCatalog();

        self::assertNull($catalog->getBlockSchema('unknown_block'));
    }

    public function testSchemaIncludesDescriptionWhenProvided(): void
    {
        $catalog = $this->createBlockCatalog([
            'hero' => [
                'name' => 'Hero',
                'fields' => [
                    'image' => [
                        'type' => 'ibexa_image',
                        'description' => 'Recommended: 1920x600 px, JPEG/PNG',
                    ],
                ],
            ],
        ]);

        $schema = $catalog->getBlockSchema('hero');

        self::assertNotNull($schema);
        self::assertSame('Recommended: 1920x600 px, JPEG/PNG', $schema['fields']['image']['description']);
    }

    public function testSchemaOmitsDescriptionWhenEmpty(): void
    {
        $catalog = $this->createBlockCatalog([
            'hero' => [
                'name' => 'Hero',
                'fields' => [
                    'title' => ['type' => 'ibexa_string'],
                ],
            ],
        ]);

        $schema = $catalog->getBlockSchema('hero');

        self::assertNotNull($schema);
        self::assertArrayNotHasKey('description', $schema['fields']['title']);
    }

    public function testSchemaExposesImageFileSizeAndAltTextConstraints(): void
    {
        $catalog = $this->createBlockCatalog([
            'hero' => [
                'name' => 'Hero',
                'fields' => [
                    'image' => [
                        'type' => 'ibexa_image',
                        'validator' => [
                            'FileSizeValidator' => ['maxFileSize' => 5],
                            'AlternativeTextValidator' => ['required' => true],
                        ],
                    ],
                ],
            ],
        ]);

        $schema = $catalog->getBlockSchema('hero');

        self::assertNotNull($schema);
        self::assertSame(5, $schema['fields']['image']['maxFileSize']);
        self::assertTrue($schema['fields']['image']['altTextRequired']);
    }

    public function testSchemaExposesStringLengthConstraints(): void
    {
        $catalog = $this->createBlockCatalog([
            'hero' => [
                'name' => 'Hero',
                'fields' => [
                    'title' => [
                        'type' => 'ibexa_string',
                        'validator' => [
                            'StringLengthValidator' => ['minStringLength' => 2, 'maxStringLength' => 100],
                        ],
                    ],
                ],
            ],
        ]);

        $schema = $catalog->getBlockSchema('hero');

        self::assertNotNull($schema);
        self::assertSame(2, $schema['fields']['title']['minLength']);
        self::assertSame(100, $schema['fields']['title']['maxLength']);
    }

    public function testSchemaExposesMatrixRowLimits(): void
    {
        $catalog = $this->createBlockCatalog([
            'hero' => [
                'name' => 'Hero',
                'fields' => [
                    'cards' => [
                        'type' => 'ibexa_matrix',
                        'settings' => [
                            'columns' => [
                                ['identifier' => 'title', 'name' => 'Title'],
                            ],
                        ],
                        'validator' => [
                            'MatrixValueValidator' => ['minimumRowCount' => 2, 'maximumRowCount' => 6],
                        ],
                    ],
                ],
            ],
        ]);

        $schema = $catalog->getBlockSchema('hero');

        self::assertNotNull($schema);
        self::assertSame(2, $schema['fields']['cards']['minRows']);
        self::assertSame(6, $schema['fields']['cards']['maxRows']);
    }

    public function testSchemaExposesRelationListMinItems(): void
    {
        $catalog = $this->createBlockCatalog([
            'hero' => [
                'name' => 'Hero',
                'fields' => [
                    'items' => [
                        'type' => 'ibexa_object_relation_list',
                        'settings' => ['selectionContentTypes' => ['card_item']],
                        'validator' => [
                            'RelationValidator' => ['minimumRelationLimit' => 1],
                        ],
                    ],
                ],
            ],
        ]);

        $schema = $catalog->getBlockSchema('hero');

        self::assertNotNull($schema);
        self::assertSame(1, $schema['fields']['items']['minItems']);
    }

    public function testSchemaExposesTranslatableFlag(): void
    {
        $catalog = $this->createBlockCatalog([
            'hero' => [
                'name' => 'Hero',
                'fields' => [
                    'title' => [
                        'type' => 'ibexa_string',
                        'translatable' => true,
                    ],
                ],
            ],
        ]);

        $schema = $catalog->getBlockSchema('hero');

        self::assertNotNull($schema);
        self::assertTrue($schema['fields']['title']['translatable']);
    }

    public function testRenderBlockSummaryIsFlatListWithoutCapabilityHeaders(): void
    {
        $catalog = $this->createBlockCatalog([
            'hero_banner' => [
                'name' => 'Hero Banner',
                'fields' => ['title' => 'ibexa_string'],
            ],
            'info_cards' => [
                'name' => 'Info Cards',
                'fields' => [
                    'cards' => [
                        'type' => 'ibexa_matrix',
                        'settings' => [
                            'columns' => [
                                ['identifier' => 'icon', 'name' => 'Icon'],
                                ['identifier' => 'title', 'name' => 'Title'],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $summary = $catalog->renderBlockSummary();

        self::assertStringStartsWith('Available block types:', $summary);
        self::assertStringNotContainsString('Hero:', $summary);
        self::assertStringNotContainsString('Cards:', $summary);
        self::assertStringNotContainsString('Text:', $summary);
        self::assertStringContainsString('- hero_banner', $summary);
        self::assertStringContainsString('- info_cards', $summary);
    }

    public function testRenderBlockSummaryReturnsEmptyForNoBlocks(): void
    {
        $summary = $this->makeCatalog()->renderBlockSummary();

        self::assertSame('', $summary);
    }

    public function testRenderBlockSummaryIncludesMatrixColumnsInline(): void
    {
        $catalog = $this->createBlockCatalog([
            'info_cards' => [
                'name' => 'Info Cards',
                'fields' => [
                    'cards' => [
                        'type' => 'ibexa_matrix',
                        'settings' => [
                            'columns' => [
                                ['identifier' => 'icon', 'name' => 'Icon'],
                                ['identifier' => 'title', 'name' => 'Title'],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $summary = $catalog->renderBlockSummary();

        self::assertStringContainsString('cards:ibexa_matrix[icon,title]', $summary);
    }

    public function testRenderBlockSummaryIncludesRelationListAllowedTypes(): void
    {
        $catalog = $this->createBlockCatalog([
            'info_cards' => [
                'name' => 'Info Cards',
                'fields' => [
                    'items' => [
                        'type' => 'ibexa_object_relation_list',
                        'settings' => ['selectionContentTypes' => ['card_item', 'feature']],
                    ],
                ],
            ],
        ]);

        $summary = $catalog->renderBlockSummary();

        self::assertStringContainsString('items:ibexa_object_relation_list<card_item|feature>', $summary);
    }

    public function testRenderBlockSummaryListsOnlyInstalledBlocks(): void
    {
        $catalog = $this->createBlockCatalog([
            'cta' => [
                'name' => 'CTA',
                'fields' => ['title' => 'ibexa_string'],
            ],
        ]);

        $summary = $catalog->renderBlockSummary();

        self::assertStringContainsString('- cta', $summary);
        self::assertStringNotContainsString('- hero_banner', $summary);
        self::assertStringNotContainsString('- paragraph', $summary);
    }

    private function makeCatalog(): BlockCatalog
    {
        $types = $this->createMock(\Ibexa\Contracts\Core\Repository\ContentTypeService::class);

        return new BlockCatalog($types, new NullAdapter(), new \Masilia\Bundle\IbexaAi\Agent\Block\ContentTypeSchemas($types));
    }
}
