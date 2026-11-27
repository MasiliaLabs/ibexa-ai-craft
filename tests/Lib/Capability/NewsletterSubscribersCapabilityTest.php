<?php

declare(strict_types=1);

namespace Masilia\IbexaAiCraft\Tests\Lib\Capability;

use Masilia\Contracts\IbexaBlocks\Subscribers;
use Masilia\IbexaAiCraft\Capability\NewsletterSubscribersCapability;
use PHPUnit\Framework\TestCase;

final class NewsletterSubscribersCapabilityTest extends TestCase
{
    private function subscribers(): Subscribers
    {
        return new class() implements Subscribers {
            private const ROWS = [
                ['email' => 'a@x.org', 'list' => 'imal', 'name' => null, 'confirmed' => true, 'active' => true, 'subscribed_at' => '2026-11-27T10:00:00+00:00'],
                ['email' => 'b@x.org', 'list' => 'fossil', 'name' => 'B', 'confirmed' => true, 'active' => true, 'subscribed_at' => '2026-11-27T11:00:00+00:00'],
            ];

            public function count(?string $list = null): int
            {
                return count($this->latest(100, $list));
            }

            public function latest(int $limit, ?string $list = null): array
            {
                return array_slice(array_values(array_filter(self::ROWS, static fn (array $r): bool => $list === null || $r['list'] === $list)), 0, $limit);
            }
        };
    }

    public function testAllTheListsByDefaultAndOneWhenAsked(): void
    {
        $capability = new NewsletterSubscribersCapability($this->subscribers());

        self::assertSame(2, $capability->execute([])->data['count']);
        $one = $capability->execute(['list' => 'fossil']);
        self::assertSame(1, $one->data['count']);
        self::assertSame(['b@x.org'], array_column($one->data['latest'], 'email'));
        self::assertArrayHasKey('list', $capability->inputSchema()['properties']);
    }
}
