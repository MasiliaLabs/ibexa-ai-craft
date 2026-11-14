<?php

declare(strict_types=1);

namespace Masilia\IbexaAiCraft\Tests\Lib\Selftest;

use Ibexa\Core\Repository\Values\ContentType\FieldDefinitionCollection;
use Masilia\IbexaAiCraft\Selftest\RequiredFields;
use Masilia\IbexaAiCraft\Tests\Lib\Catalog\BlockCatalogFactoryTrait;
use Masilia\IbexaAiCraft\Tests\Lib\Catalog\FakeContentType;
use Masilia\IbexaAiCraft\Tests\Lib\Catalog\FakeFieldDefinition;
use PHPUnit\Framework\TestCase;

final class RequiredFieldsTest extends TestCase
{
    use BlockCatalogFactoryTrait;

    private function type(array $fields): FakeContentType
    {
        $definitions = [];
        foreach ($fields as $id => [$type, $required]) {
            $definitions[] = new FakeFieldDefinition($id, $type, [], [], $required);
        }

        return new FakeContentType('block', 'Block', new FieldDefinitionCollection($definitions));
    }

    public function testRequiredPlainFieldsGetAValueAndOptionalOnesNone(): void
    {
        $type = $this->type(['title' => ['ibexa_string', true], 'body' => ['ibexa_richtext', true], 'count' => ['ibexa_integer', true], 'flag' => ['ibexa_boolean', true], 'note' => ['ibexa_text', false]]);

        self::assertSame(['title' => 'T', 'body' => '<p>T</p>', 'count' => 1, 'flag' => true], RequiredFields::for($type, 'T'));
    }

    public function testATypeWithARequiredFieldItCannotFillGetsNull(): void
    {
        self::assertNull(RequiredFields::for($this->type(['title' => ['ibexa_string', true], 'image' => ['ibexa_image', true]]), 'T'));
    }
}
