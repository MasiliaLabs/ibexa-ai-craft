<?php

declare(strict_types=1);

namespace Masilia\IbexaAiCraft\Tests\Catalog;

use Ibexa\Contracts\Core\Repository\ContentTypeService;
use Ibexa\Contracts\Core\Repository\Values\ContentType\ContentTypeGroup;
use Ibexa\Core\Repository\Values\ContentType\ContentType as CoreContentType;
use Ibexa\Core\Repository\Values\ContentType\FieldDefinition as CoreFieldDefinition;
use Ibexa\Core\Repository\Values\ContentType\FieldDefinitionCollection;
use Masilia\Bundle\IbexaAiCraft\Catalog\BlockCatalog;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * Test double built on Ibexa's own value object: only properties are set, no method is overridden,
 * so it keeps matching the Ibexa 5 signatures.
 *
 * @internal test-only
 */
final class FakeContentType extends CoreContentType
{
    public function __construct(
        string $identifier,
        string $name,
        FieldDefinitionCollection $fieldDefinitions,
    ) {
        parent::__construct([
            'identifier' => $identifier,
            'names' => ['eng-GB' => $name],
            'descriptions' => [],
            'mainLanguageCode' => 'eng-GB',
            'fieldDefinitions' => $fieldDefinitions,
        ]);
    }
}

/**
 * @internal test-only
 */
final class FakeFieldDefinition extends CoreFieldDefinition
{
    /**
     * @param array<string, mixed> $fieldSettings
     * @param array<string, mixed> $validatorConfiguration
     */
    public function __construct(
        string $identifier,
        string $fieldTypeIdentifier,
        array $fieldSettings = [],
        array $validatorConfiguration = [],
        bool $isRequired = false,
        string $description = '',
        bool $isTranslatable = false,
    ) {
        parent::__construct([
            'identifier' => $identifier,
            'fieldTypeIdentifier' => $fieldTypeIdentifier,
            'fieldSettings' => $fieldSettings,
            'validatorConfiguration' => $validatorConfiguration,
            'isRequired' => $isRequired,
            'isTranslatable' => $isTranslatable,
            'names' => ['eng-GB' => $identifier],
            'descriptions' => $description !== '' ? ['eng-GB' => $description] : [],
            'mainLanguageCode' => 'eng-GB',
        ]);
    }
}

trait BlockCatalogFactoryTrait
{
    /**
     * Build a real {@see BlockCatalog} from a plain array of block definitions.
     *
     * BlockCatalog is final, so tests cannot mock it. This helper creates a
     * catalog backed by a stubbed ContentTypeService and an in-memory cache,
     * which is enough for unit tests that only care about the catalog output.
     *
     * Field definitions can be declared as:
     *   'field_id' => 'ibexa_string'
     * or, when field settings / validator / required are needed:
     *   'field_id' => [
     *     'type' => 'ibexa_string',
     *     'settings' => ['...'],
     *     'validator' => ['StringLengthValidator' => ['minStringLength' => 1]],
     *     'required' => true,
     *   ]
     *
     * @param array<string, array{name: string, fields: array<string, string|array{type: string, settings?: array<string, mixed>, validator?: array<string, mixed>, required?: bool}>}> $blocks
     */
    private function createBlockCatalog(array $blocks): BlockCatalog
    {
        $contentTypes = [];
        foreach ($blocks as $identifier => $block) {
            $fieldDefinitions = [];
            foreach ($block['fields'] as $fieldIdentifier => $fieldConfig) {
                if (is_string($fieldConfig)) {
                    $fieldConfig = ['type' => $fieldConfig];
                }

                $fieldDefinitions[] = new FakeFieldDefinition(
                    $fieldIdentifier,
                    $fieldConfig['type'],
                    $fieldConfig['settings'] ?? [],
                    $fieldConfig['validator'] ?? [],
                    $fieldConfig['required'] ?? false,
                    $fieldConfig['description'] ?? '',
                    $fieldConfig['translatable'] ?? false,
                );
            }

            $contentTypes[] = new FakeContentType(
                $identifier,
                $block['name'],
                new FieldDefinitionCollection($fieldDefinitions),
            );
        }

        $group = $this->createStub(ContentTypeGroup::class);

        $contentTypeService = $this->createStub(ContentTypeService::class);
        $contentTypeService->method('loadContentTypeGroupByIdentifier')
            ->with('Blocks')
            ->willReturn($group);
        $contentTypeService->method('loadContentTypes')
            ->with($group)
            ->willReturn($contentTypes);

        return new BlockCatalog($contentTypeService, new ArrayAdapter(), new \Masilia\Bundle\IbexaAi\Agent\Block\ContentTypeSchemas($contentTypeService));
    }
}
