<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAiCraft\Command;

use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Ibexa\Core\Repository\Values\User\UserReference;
use Masilia\Contracts\IbexaAi\Site\SiteLocations;
use Masilia\Bundle\IbexaAiCraft\Mining\ConventionMiner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'masilia:ai:craft:mine',
    description: 'Read the pages of a site and show how they are built (blocks, order, what is filled in): what site.conventions tells the assistant.',
)]
final class MineConventionsCommand extends Command
{
    public function __construct(
        private readonly ConventionMiner $miner,
        private readonly SiteLocations $resolver,
        private readonly PermissionResolver $permissions,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('siteaccess', InputArgument::REQUIRED, 'The front-office siteaccess')
            ->addOption('pages', 'p', InputOption::VALUE_REQUIRED, 'How many of the latest pages to read', '60')
            ->addOption('user', 'u', InputOption::VALUE_REQUIRED, 'Id of the user whose rights are used to read', '14')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Print the result as JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $this->permissions->setCurrentUserReference(new UserReference((int) $input->getOption('user')));

        $root = $this->resolver->rootLocationId((string) $input->getArgument('siteaccess'));
        if ($root === null || $root <= 0) {
            $io->error('That siteaccess has no root location.');

            return Command::FAILURE;
        }
        $result = $this->miner->mine($root, (int) $input->getOption('pages'));

        if ($input->getOption('json')) {
            $output->writeln((string) json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return Command::SUCCESS;
        }
        if ($result['pages'] === 0) {
            $io->warning(sprintf('No page of type "%s" with blocks under location %d.', $result['page_type'], $root));

            return Command::SUCCESS;
        }

        $io->title(sprintf('%d "%s" pages under location %d', $result['pages'], $result['page_type'], $root));
        $io->writeln(sprintf('Blocks per page: %.1f on average (%d to %d).', $result['blocks_per_page']['avg'], $result['blocks_per_page']['min'], $result['blocks_per_page']['max']));
        $io->section('Block types');
        $io->table(
            ['Type', 'Uses', 'On % of pages', 'Child items', 'Fields filled'],
            array_map(static fn (array $b): array => [
                $b['type'],
                $b['uses'],
                (int) round($b['share'] * 100) . '%',
                $b['avg_children'] ?? '',
                implode(', ', array_map(static fn (string $f, float $r): string => sprintf('%s %d%%', $f, round($r * 100)), array_keys($b['fields']), $b['fields'])),
            ], $result['block_types']),
        );
        $io->writeln('First block: ' . implode(', ', array_map(static fn (array $f): string => sprintf('%s (%d%%)', $f['type'], round($f['share'] * 100)), $result['first_block'])));
        $io->writeln('Last block:  ' . implode(', ', array_map(static fn (array $f): string => sprintf('%s (%d%%)', $f['type'], round($f['share'] * 100)), $result['last_block'])));
        $io->section('Common sequences');
        $io->listing(array_map(static fn (array $s): string => sprintf('%d page(s): %s', $s['pages'], implode(' > ', $s['blocks'])), $result['sequences']));
        if ($result['accepted_but_unused'] !== []) {
            $io->writeln('Accepted by the page but not used in the sample: ' . implode(', ', $result['accepted_but_unused']));
        }

        return Command::SUCCESS;
    }
}
