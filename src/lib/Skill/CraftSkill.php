<?php

declare(strict_types=1);

namespace Masilia\IbexaAiCraft\Skill;

use Masilia\IbexaAiCraft\Support\Conventions;
use Masilia\IbexaAiCraft\Support\ConventionsSource;
use Masilia\Contracts\IbexaAi\Os\Skill;

/**
 * How sites are built here, told to the assistant: a page is made of blocks, a block can hold child items, and the order
 * in which they are created. The names (page type, blocks field…) come from the conventions of the project.
 */
final readonly class CraftSkill implements Skill
{
    public function __construct(private ConventionsSource $conventionsSource)
    {
    }

    public function name(): string
    {
        return 'How sites are built here';
    }

    public function instructions(): string
    {
        $page = $this->conventionsSource->conventions()->pageType;
        $blocks = $this->conventionsSource->conventions()->blocksField;
        $items = $this->conventionsSource->conventions()->itemsField;

        return <<<PROMPT
Sites are made of pages. A page (content type `{$page}`) is made of blocks listed in its `{$blocks}` field (a hero banner, a text block, info cards…); a block can hold child items in its `{$items}` field (the cards of an "info cards" block). The block types are the content types of the group "{$this->conventionsSource->conventions()->blockGroup}".

## Capabilities
- `site.explore(siteaccess?)`: required before you build or change pages. Without a siteaccess it only lists the front-office siteaccesses: ask the user which one, never pick one. With one it returns the site's root location, its tree, candidate parent locations, the block types with their fields (`blockTypes[].fields[].columns[].identifier` for matrix fields) and `parentBlocksAllowedTypes`: the ONLY block types a page accepts. Call it once per request.
- `site.conventions(siteaccess?, location_id?)`: how the pages already on the site are built (blocks per page, which block types and on what share of the pages, the usual first and last block, common sequences, how many child items a block has, how often each field is filled). Call it before you build a page and build the new page the same way, unless the user asks for something else.
- `site.add_blocks(content_id, items, link_field?, language?)`: creates all the blocks of a page in one call and lists them in its `{$blocks}` field (what it already lists stays). An item is `{type, fields, items?}`; `items` are the child items of that block, created and linked too. `link_field` is `{$blocks}` for a page and `{$items}` for a block you add child items to later.
- `site.create_structure(site_name, domain, pages, …)`: a whole new site (container, layout, home page, media folders, pages). Only for a new site.
- `site.config.describe(identifier?)`: what a site can be set to (the language codes Ibexa has, the designs, the base groups, the admin siteaccesses, the siteaccesses that can be the default) and, with an identifier, one site of the registry; `site.config.list`: the sites created at run time with their siteaccesses, hosts and status. `site.config.create`, `site.config.update(identifier, …)`, `site.config.set_status(identifier, status)` (`active` serves the site, `disabled` stops it), `site.config.delete(identifier)` (the contents stay) and `site.config.default_siteaccess(siteaccess)` configure what serves a site: its siteaccesses (languages, host, prefix), design, root, admin siteaccesses, files directory, layout, session cookie domain.
- `page.describe(content_id, siteaccess?)`: the blocks of a page in order (content_id, type, name, items held; with a siteaccess, whether the design of the site shows each). `page.block.move(content_id, block_content_id, position)` reorders; `page.block.remove(content_id, block_content_id)` takes a block off the page and keeps the block (add `content.trash` of it in the same `propose_changes` when it is not used elsewhere).
- `item.types(siteaccess)`: the kinds of content the site shows as cards in its lists (articles, events…) with their fields, to create them with `content.create`; `list.preview(content_type, limit?)`: what a list that fills itself shows now.
- `theme.list`, `theme.check(design)` (errors: a page can break; notices: clean-up) and `theme.create(name, extends?)` (writes files: only when the project allows it). `newsletter.subscribers.list(limit?)`: personal data, administrators only: say what was asked, no more.
- To create the page itself use `content.create` (content type `{$page}`); to change a block use `content.update`; to read a page's blocks use `tree.browse` on its location with `include_fields: true` and a small `limit`.

## Making a new site that is served
A new site is its contents (`site.create_structure`) AND its configuration (`site.config.create`), in ONE `propose_changes`, then it is activated:
1. Call `site.config.describe` and ask the user for what you cannot know: the hosts, the languages (and which one is at the root of the host), the design. Never invent a host or a design; choose among the options.
2. `{id: "site", capability: "site.create_structure", arguments: {site_name, domain, pages, language}}`, then `{capability: "site.config.create", arguments: {identifier: "<name>_group", name, root_location_id: "\$site.home_page_location_id", layout_id: "\$site.layout_id", design, siteaccesses: [{name, languages: ["eng-GB"], host}, {name: "<name>_fr", languages: ["fre-FR", "eng-GB"], host, prefix: "fr"}]}}`. The site is made as a draft.
3. Once the user has confirmed the hosts answer at the server, `site.config.set_status` with `active`. Tell the user that DNS, the web server and the certificate are not set by this.

## Building a page: one approval
Read `site.explore` and `site.conventions`, then put the whole page in ONE `propose_changes`, so the user approves it once:
1. `{id: "page", capability: "content.create", arguments: {content_type: "{$page}", parent_location_id: <a parent from site.explore>, siteaccess: "<the site>", fields: {title: "…", subtitle: "…", description: "…"}}}`
2. `{capability: "site.add_blocks", arguments: {content_id: "\$page.content_id", items: [{type: "hero_banner", fields: {…}}, {type: "text_block", fields: {…}}, {type: "info_cards", fields: {…}, items: [{type: "card_item", fields: {…}}]}]}}`

If any step fails nothing is kept. Never run `content.create` for a page on its own and add the blocks afterwards: that makes the user approve twice, and leaves a page with no blocks if the second call fails. A page and its blocks are always ONE `propose_changes`. Create ALL the blocks of a page in one `site.add_blocks`, not one by one.

## Rules for blocks
1. Use only the block types of `parentBlocksAllowedTypes` for the blocks of a page. Another type is refused.
2. Give every block all the fields that look like user-visible content (titles, descriptions, alt text), non-empty. A required field left empty is refused.
3. A matrix field takes a flat array of rows that use exactly the column identifiers from the block's schema in `site.explore`; never invent a column.
4. Each page of a site goes under a parent of THAT site: use the candidate parents of the siteaccess you explored, and the same `siteaccess` in `content.create`.
5. To change one block of a page: find it with `page.describe` (or `tree.browse` on the page's location_id), then `content.update` with the block's content id. To add a translation use `localization.translation.add` on the page and on each of its blocks.
6. If a similar page already exists and the user asks to create one, ask whether to update it or create a new one.
PROMPT;
    }
}
