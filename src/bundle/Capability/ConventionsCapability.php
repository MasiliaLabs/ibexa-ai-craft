<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAiCraft\Capability;

use Ibexa\Contracts\Core\Repository\Repository;
use Masilia\Contracts\IbexaAi\Site\SiteLocations;
use Masilia\Contracts\IbexaAi\Os\AbstractCapability;
use Masilia\Bundle\IbexaAiCraft\Mining\ConventionMiner;
use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Risk;
use Throwable;

final class ConventionsCapability extends AbstractCapability
{
    public function __construct(
        private readonly ConventionMiner $miner,
        private readonly SiteLocations $resolver,
        private readonly Repository $repository,
    ) {
    }

    public function name(): string
    {
        return 'site.conventions';
    }

    public function summary(): string
    {
        return 'How the existing pages of a site are built: the blocks they use, in which order, and what is filled in.';
    }

    public function description(): string
    {
        return 'Reads the pages already on a site (the latest ones) and reports the pattern: how many blocks a page has, which block types are used and on what share of the pages, '
            . 'which block usually comes first and last, the common sequences and transitions, how many child items a block has, and for each block type how often each field is filled. '
            . 'Call it before you build a page, and build the new page the way the site\'s pages are built (same kinds of blocks, same order, same care in the fields), unless the user asks otherwise. '
            . 'Give siteaccess, or location_id of a section of the site. accepted_but_unused lists block types the page accepts that the sample never uses.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'siteaccess' => ['type' => 'string', 'description' => 'The front-office siteaccess the user named'],
                'location_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Or a location: only the pages under it are read'],
                'pages' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'description' => 'How many of the latest pages to read (default 60)'],
            ],
        ];
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
        return [['description' => 'How pages are built on a site', 'arguments' => ['siteaccess' => 'fossil_exit']]];
    }

    public function validate(array $arguments): ?string
    {
        if (!isset($arguments['siteaccess']) && !isset($arguments['location_id'])) {
            return 'Give a siteaccess or a location_id.';
        }

        return $this->root($arguments) === null ? 'That siteaccess has no root location: see site.explore for the sites.' : null;
    }

    public function execute(array $arguments): CapabilityResult
    {
        $root = $this->root($arguments);
        if ($root === null) {
            return CapabilityResult::error('That siteaccess has no root location.');
        }
        $result = $this->miner->mine($root, (int) ($arguments['pages'] ?? 60));
        if ($result['pages'] === 0) {
            return CapabilityResult::ok('There are no pages with blocks under that location yet: build the first one from the block types site.explore gives.', $result);
        }

        return CapabilityResult::ok(sprintf('%d page(s) read: %.1f blocks per page on average', $result['pages'], $result['blocks_per_page']['avg']), $result);
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function root(array $arguments): ?int
    {
        if (isset($arguments['location_id'])) {
            try {
                $this->repository->getLocationService()->loadLocation((int) $arguments['location_id']);

                return (int) $arguments['location_id'];
            } catch (Throwable) {
                return null;
            }
        }
        $root = $this->resolver->rootLocationId((string) $arguments['siteaccess']);

        return $root !== null && $root > 0 ? $root : null;
    }
}
