<?php

declare(strict_types=1);

namespace Masilia\IbexaAiCraft\Selftest;

use Ibexa\Contracts\Core\Repository\Values\ContentType\ContentType;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;

/**
 * Plain values for the required fields of a content type, so that a test can create a content of any type it finds
 * without knowing it. A type with a required field of a kind it cannot fill (an image, a relation) gets null.
 */
final class RequiredFields
{
    /**
     * @return array<string, mixed>|null Field identifier => value, or null when a required field cannot be filled
     */
    public static function for(ContentType $type, string $text): ?array
    {
        $fields = [];
        foreach ($type->getFieldDefinitions() as $field) {
            if (!$field->isRequired) {
                continue;
            }
            $value = self::value($field, $text);
            if ($value === null) {
                return null;
            }
            $fields[$field->identifier] = $value;
        }

        return $fields;
    }

    private static function value(FieldDefinition $field, string $text): string|int|bool|null
    {
        return match ($field->fieldTypeIdentifier) {
            'ibexa_string', 'ibexa_text' => $text,
            'ibexa_richtext' => '<p>' . $text . '</p>',
            'ibexa_integer' => 1,
            'ibexa_boolean' => true,
            default => null,
        };
    }
}
