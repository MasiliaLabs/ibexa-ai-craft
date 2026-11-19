---
name: masilia-ibexa-ai-craft
description: Use and configure masilia/ibexa-ai-craft, the layer that lets the Ibexa AI assistant build sites and pages out of blocks (page.*, site.*, site.config.*, theme.* capabilities, conventions, items_field_by_type, theme_creation). Use whenever the user wants the assistant to create a site, a home page, pages or blocks, check a theme, list block types, or when the assistant fails to add blocks or child items to a page, or mentions masilia/ibexa-ai-craft or "craft", even without the package name.
---

# masilia/ibexa-ai-craft

Teaches the assistant (`masilia/ibexa-ai`) how a site is made: a page holds blocks, a block may hold items, a theme gives them templates. It reads your conventions (shared with `masilia/ibexa-blocks`) and sends every change through the approval flow. It requires `ibexa-ai`, `ibexa-blocks` and `ibexa-sites` (Composer installs them; enable their bundles and follow their READMEs).

## Capabilities (what to ask the assistant)
- `page.*` / `site.*`: explore a site, mine its conventions from its own pages, create a site structure (container, layout, home page, media folders) in one transaction, add blocks and child items, move/remove blocks.
- `site.config.*`: list, describe, create, update, set status, delete, default siteaccess, over `ibexa-sites`.
- `theme.*`: check a design (every block type shown has a template), list themes, and `theme.create` (writes files: **off** unless `masilia_ibexa_ai_craft.theme_creation: true`).
- Lists, item kinds, newsletter subscribers (contracts of `ibexa-blocks`). `masilia:ai:os:selftest <locationId>` includes a page-of-blocks scenario.

## Configure
```yaml
masilia_ibexa_ai_craft:
    conventions:            # shared with masilia_ibexa_blocks when that is configured
        page_type: page
        blocks_field: blocks
        items_field: items
        items_field_by_type: { info_cards: cards }
        block_group: Blocks
        site_type: site
        home_page_type: home_page
        layout_type: layout_config
        folder_type: folder
    theme_creation: false
```
The conventions must match the real content model. Wrong names are the first cause of "the assistant cannot add blocks": check `page_type`, the blocks field, and `items_field_by_type` for block types whose children field is not `items` (for example `cards`).

## Troubleshooting
- "has no list-of-relations field items (it has: cards)": declare `items_field_by_type` for that block type (in `masilia_ibexa_blocks`, craft reads it).
- "no content type X to build a site with": install the content model first (Kaliop migrations or `masilia:blocks:model:install` from ibexa-blocks), then retry.
- A site is created as draft by the registry; activating serves its hosts: ask the user, do not do it silently.
- A block is refused on a page: the page's blocks field restricts the types (`selectionContentTypes`); the block must be allowed there.
- Craft's required fields for site creation are checked before anything is written; a layout needs its required fields fillable by the builder.

Do not run `theme.create` without the user asking: it writes files in the project.
