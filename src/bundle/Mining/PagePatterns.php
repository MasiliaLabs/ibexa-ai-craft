<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAiCraft\Mining;

/**
 * What a set of pages says about how pages are built here: which blocks they use, in what order, with what filled in.
 * It works on a plain description of the pages (see summarize), so it knows nothing of Ibexa.
 */
final class PagePatterns
{
    /**
     * @param list<array{title?: string, blocks: list<array{type: string, children?: int, filled?: array<string, bool>}>}> $pages
     *        One entry per page, its blocks in order; "filled" says which fields of the block have a value
     *
     * @return array{
     *     pages: int,
     *     blocks_per_page: array{avg: float, min: int, max: int},
     *     block_types: list<array{type: string, uses: int, pages: int, share: float, avg_children: float|null, fields: array<string, float>}>,
     *     first_block: list<array{type: string, share: float}>,
     *     last_block: list<array{type: string, share: float}>,
     *     transitions: list<array{from: string, to: string, count: int}>,
     *     sequences: list<array{blocks: list<string>, pages: int}>
     * }
     */
    public static function summarize(array $pages, int $top = 12): array
    {
        $pages = array_values(array_filter($pages, static fn (array $page): bool => $page['blocks'] !== []));
        $count = count($pages);
        if ($count === 0) {
            return ['pages' => 0, 'blocks_per_page' => ['avg' => 0.0, 'min' => 0, 'max' => 0], 'block_types' => [], 'first_block' => [], 'last_block' => [], 'transitions' => [], 'sequences' => []];
        }

        $sizes = array_map(static fn (array $page): int => count($page['blocks']), $pages);
        $uses = [];
        $inPages = [];
        $children = [];
        $filled = [];
        $first = [];
        $last = [];
        $transitions = [];
        $sequences = [];

        foreach ($pages as $page) {
            $types = array_map(static fn (array $block): string => $block['type'], $page['blocks']);
            $first[$types[0]] = ($first[$types[0]] ?? 0) + 1;
            $last[$types[array_key_last($types)]] = ($last[$types[array_key_last($types)]] ?? 0) + 1;
            $sequenceKey = implode('>', $types);
            $sequences[$sequenceKey] = ($sequences[$sequenceKey] ?? ['blocks' => $types, 'pages' => 0]);
            ++$sequences[$sequenceKey]['pages'];

            foreach (array_unique($types) as $type) {
                $inPages[$type] = ($inPages[$type] ?? 0) + 1;
            }
            foreach ($page['blocks'] as $index => $block) {
                $type = $block['type'];
                $uses[$type] = ($uses[$type] ?? 0) + 1;
                if (isset($block['children'])) {
                    $children[$type][] = $block['children'];
                }
                foreach ($block['filled'] ?? [] as $field => $isFilled) {
                    $filled[$type][$field]['seen'] = ($filled[$type][$field]['seen'] ?? 0) + 1;
                    $filled[$type][$field]['filled'] = ($filled[$type][$field]['filled'] ?? 0) + ($isFilled ? 1 : 0);
                }
                if ($index > 0) {
                    $key = $types[$index - 1] . '>' . $type;
                    $transitions[$key] = ($transitions[$key] ?? ['from' => $types[$index - 1], 'to' => $type, 'count' => 0]);
                    ++$transitions[$key]['count'];
                }
            }
        }

        arsort($uses);
        $blockTypes = [];
        foreach (array_slice($uses, 0, $top, true) as $type => $n) {
            $fields = [];
            foreach ($filled[$type] ?? [] as $field => $stats) {
                $fields[$field] = round($stats['filled'] / $stats['seen'], 2);
            }
            ksort($fields);
            $blockTypes[] = [
                'type' => (string) $type,
                'uses' => $n,
                'pages' => $inPages[$type],
                'share' => round($inPages[$type] / $count, 2),
                'avg_children' => isset($children[$type]) ? round(array_sum($children[$type]) / count($children[$type]), 1) : null,
                'fields' => $fields,
            ];
        }

        $share = static function (array $tally) use ($count): array {
            arsort($tally);

            return array_map(static fn (string $type, int $n): array => ['type' => $type, 'share' => round($n / $count, 2)], array_keys(array_slice($tally, 0, 3, true)), array_slice($tally, 0, 3, true));
        };
        usort($transitions, static fn (array $a, array $b): int => $b['count'] <=> $a['count'] ?: strcmp($a['from'] . $a['to'], $b['from'] . $b['to']));
        usort($sequences, static fn (array $a, array $b): int => $b['pages'] <=> $a['pages'] ?: strcmp(implode($a['blocks']), implode($b['blocks'])));

        return [
            'pages' => $count,
            'blocks_per_page' => ['avg' => round(array_sum($sizes) / $count, 1), 'min' => min($sizes), 'max' => max($sizes)],
            'block_types' => $blockTypes,
            'first_block' => $share($first),
            'last_block' => $share($last),
            'transitions' => array_slice($transitions, 0, 8),
            'sequences' => array_slice($sequences, 0, 5),
        ];
    }
}
