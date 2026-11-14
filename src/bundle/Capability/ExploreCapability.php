<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAiCraft\Capability;

use Masilia\Contracts\IbexaAi\Os\AbstractCapability;
use Masilia\Bundle\IbexaAiCraft\Site\SiteExplorer;
use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Risk;

final class ExploreCapability extends AbstractCapability
{
    public function __construct(private readonly SiteExplorer $explorer)
    {
    }

    public function name(): string
    {
        return 'site.explore';
    }

    public function summary(): string
    {
        return 'Find the front-office sites, and for one of them its tree, where a page can go and which blocks a page accepts.';
    }

    public function description(): string
    {
        return 'Without a siteaccess it only lists the front-office siteaccesses: ask the user which one they mean, never pick one. '
            . 'With one (fuzzy match: a name with spaces or other casing matches) it returns the root location of the site, the site tree below it, '
            . 'candidate parent locations for a page, the block types, and parentBlocksAllowedTypes: the only block types the blocks field of a page accepts. '
            . 'Call it once per request that builds or changes pages.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => ['siteaccess' => ['type' => 'string', 'description' => 'The siteaccess the user named; leave it out to get the list']]];
    }

    public function risk(): Risk
    {
        return Risk::READ;
    }

    public function requires(): array
    {
        return [['content', 'read']];
    }

    public function examples(): array
    {
        return [['description' => 'The site the user named', 'arguments' => ['siteaccess' => 'fossil_exit']], ['description' => 'Which sites are there', 'arguments' => []]];
    }

    public function execute(array $arguments): CapabilityResult
    {
        $siteaccess = trim((string) ($arguments['siteaccess'] ?? ''));
        $result = $this->explorer->explore($siteaccess);

        if ($result->hasError()) {
            return CapabilityResult::error($result->errorMessage ?? 'Exploration failed');
        }
        if ($siteaccess === '') {
            return CapabilityResult::ok('No siteaccess was given. Available siteaccesses: ' . implode(', ', $result->siteaccesses) . '. Ask the user which one they mean; do not pick one yourself.', $result->toArray());
        }
        if (!$result->hasMatchedSiteaccess()) {
            return CapabilityResult::ok(sprintf('Siteaccess "%s" not found. Available siteaccesses: %s', $siteaccess, implode(', ', $result->siteaccesses)), $result->toArray());
        }

        return CapabilityResult::ok(sprintf('Explored %d siteaccesses. Matched: %s (root location %d).', count($result->siteaccesses), $result->matchedSiteaccess, $result->rootLocationId ?? 0), $result->toArray());
    }
}
