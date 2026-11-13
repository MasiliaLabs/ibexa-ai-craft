<?php

declare(strict_types=1);

namespace Masilia\IbexaAiCraft\Tests\Mining;

use Masilia\Bundle\IbexaAiCraft\Mining\PagePatterns;
use PHPUnit\Framework\TestCase;

final class PagePatternsTest extends TestCase
{
    private static function block(string $type, array $filled = [], ?int $children = null): array
    {
        return ['type' => $type] + ($filled !== [] ? ['filled' => $filled] : []) + ($children !== null ? ['children' => $children] : []);
    }

    public function testNoPageHasNoPattern(): void
    {
        $summary = PagePatterns::summarize([['blocks' => []]]);

        self::assertSame(0, $summary['pages']);
        self::assertSame([], $summary['block_types']);
    }

    public function testItSaysHowPagesAreBuilt(): void
    {
        $pages = [
            ['blocks' => [self::block('hero', ['title' => true, 'image' => true]), self::block('text', ['title' => true]), self::block('cards', [], 3)]],
            ['blocks' => [self::block('hero', ['title' => true, 'image' => false]), self::block('text', ['title' => false]), self::block('cards', [], 5)]],
            ['blocks' => [self::block('hero', ['title' => true, 'image' => true]), self::block('cards', [], 4)]],
            ['blocks' => [self::block('text')]],
        ];

        $summary = PagePatterns::summarize($pages);

        self::assertSame(4, $summary['pages']);
        self::assertSame(['avg' => 2.3, 'min' => 1, 'max' => 3], $summary['blocks_per_page']);

        $byType = array_column($summary['block_types'], null, 'type');
        self::assertSame(3, $byType['hero']['uses']);
        self::assertSame(0.75, $byType['hero']['share']);
        self::assertSame(['image' => 0.67, 'title' => 1.0], $byType['hero']['fields']);
        self::assertSame(0.5, $byType['text']['fields']['title']);
        self::assertSame(4.0, $byType['cards']['avg_children']);
        self::assertNull($byType['hero']['avg_children']);

        self::assertSame([['type' => 'hero', 'share' => 0.75], ['type' => 'text', 'share' => 0.25]], $summary['first_block']);
        self::assertSame('cards', $summary['last_block'][0]['type']);
        self::assertSame(['from' => 'hero', 'to' => 'text', 'count' => 2], $summary['transitions'][0]);
        self::assertSame(['blocks' => ['hero', 'text', 'cards'], 'pages' => 2], array_merge($summary['sequences'][0], ['pages' => 2]));
    }

    public function testTheMostCommonSequenceComesFirst(): void
    {
        $same = ['blocks' => [self::block('a'), self::block('b')]];
        $summary = PagePatterns::summarize([$same, $same, ['blocks' => [self::block('b')]]]);

        self::assertSame(['blocks' => ['a', 'b'], 'pages' => 2], $summary['sequences'][0]);
    }
}
