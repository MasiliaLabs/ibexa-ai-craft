<?php

declare(strict_types=1);

namespace Masilia\IbexaAiCraft\Tests\Skill;

use Masilia\Bundle\IbexaAiCraft\Skill\CraftSkill;
use Masilia\Bundle\IbexaAiCraft\Support\Conventions;
use Masilia\IbexaAi\Os\Skill;
use PHPUnit\Framework\TestCase;

final class CraftSkillTest extends TestCase
{
    public function testItTeachesTheCapabilitiesAndTheFlowWithTheNamesOfTheProject(): void
    {
        $skill = new CraftSkill(Conventions::fromSettings(['page_type' => 'landing', 'blocks_field' => 'sections', 'items_field' => 'cards', 'block_group' => 'Sections']));

        self::assertInstanceOf(Skill::class, $skill);
        $text = $skill->instructions();
        foreach (['site.explore', 'site.add_blocks', 'site.create_structure', 'propose_changes', '$page.content_id', 'parentBlocksAllowedTypes'] as $expected) {
            self::assertStringContainsString($expected, $text);
        }
        foreach (['landing', 'sections', 'cards', '"Sections"'] as $name) {
            self::assertStringContainsString($name, $text, 'the names come from the conventions');
        }
        self::assertStringNotContainsString('{$', $text, 'no placeholder is left');
    }
}
