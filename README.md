# Ibexa AI Craft

[![CI](https://github.com/MasiliaLabs/ibexa-ai-craft/actions/workflows/ci.yml/badge.svg)](https://github.com/MasiliaLabs/ibexa-ai-craft/actions/workflows/ci.yml)
[![Latest version](https://img.shields.io/packagist/v/masilia/ibexa-ai-craft.svg)](https://packagist.org/packages/masilia/ibexa-ai-craft)
[![PHP](https://img.shields.io/packagist/php-v/masilia/ibexa-ai-craft.svg)](https://packagist.org/packages/masilia/ibexa-ai-craft)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

Lets the Ibexa AI assistant build sites and pages out of blocks: page composition, site structures, themes and lists.

`masilia/ibexa-ai-craft` is part of a family of packages for [Ibexa](https://www.ibexa.co) that work together or alone. See [the other packages](#the-masilia-packages).

## What it is

A layer on [`masilia/ibexa-ai`](https://github.com/MasiliaLabs/ibexa-ai) that teaches the assistant how a site is built **out of blocks**: a page holds blocks, a block may hold items, a theme gives them templates. It reads the conventions of your project instead of assuming them, and every change goes through the assistant's approval flow.

## Features

- **Pages and sites**: `page.*` and `site.*` capabilities to explore a site, mine its conventions from its own pages, create a site structure (container, home page, layout configuration) in one transaction, and add blocks and their child items to a page.
- **Site registry**: `site.config.*` capabilities (list, describe, create, update, set status, delete, default siteaccess) over [`masilia/ibexa-sites`](https://github.com/MasiliaLabs/ibexa-sites).
- **Themes**: `theme.*` capabilities to check the design of a site (every block type shown has a template) and, when you allow it, to create a theme that extends another. Writing files is **off by default**.
- **Lists, item kinds and newsletter subscribers** as capabilities, on the contracts of `masilia/ibexa-blocks`.
- **Self-test scenario** for the page-of-blocks flow, added to `masilia:ai:os:selftest`.

## Requirements

- PHP 8.3 or later
- Ibexa 5.0 (Symfony 7.4)

Installed with it by Composer:

- [`masilia/ibexa-ai`](https://github.com/MasiliaLabs/ibexa-ai) the assistant itself
- [`masilia/ibexa-blocks`](https://github.com/MasiliaLabs/ibexa-blocks) the content model of pages made of blocks
- [`masilia/ibexa-sites`](https://github.com/MasiliaLabs/ibexa-sites) the registry of sites

## Installation

```bash
composer require masilia/ibexa-ai-craft
```

Composer installs `masilia/ibexa-ai`, `masilia/ibexa-blocks` and `masilia/ibexa-sites` with it. Enable what is new in `config/bundles.php`:

```php
Masilia\Bundle\IbexaAdminKit\MasiliaIbexaAdminKitBundle::class => ['all' => true],
Masilia\Bundle\IbexaAi\MasiliaIbexaAiBundle::class => ['all' => true],
Masilia\Bundle\IbexaBlocks\MasiliaIbexaBlocksBundle::class => ['all' => true],
Masilia\Bundle\IbexaSites\MasiliaIbexaSitesBundle::class => ['all' => true],
Masilia\Bundle\IbexaAiCraft\MasiliaIbexaAiCraftBundle::class => ['all' => true],
```

See the README of each of them for their own routes, migrations and assets.

## Configuration

The conventions are shared with `masilia_ibexa_blocks` when it is configured; set them here only for what differs.

```yaml
masilia_ibexa_ai_craft:
    conventions:
        page_type: page                 # the content type of a page
        blocks_field: blocks            # the field of a page that holds its blocks
        items_field: items              # the field of a block that holds its child items
        items_field_by_type:            # block types that call it differently
            info_cards: cards
        block_group: Blocks             # the content type group of the blocks
        site_type: site
        home_page_type: home_page
        layout_type: layout_config
        folder_type: folder
    theme_creation: false               # true lets theme.create write the files of a new theme
```

## Usage

Ask the assistant in the chat. For instance: *"Which blocks can I put on the home page of the shop site?"*, *"Add a hero and three cards to the About page"*, *"Create a site called Acme Shop on acme.example.org"*. Each change is shown in an approval card first.

## Tests

```bash
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse --memory-limit=2G --no-progress
```

## AI agent skill

This package ships a skill for AI coding agents (Claude Code and other agents that read `SKILL.md` skills): how to install, configure, operate and troubleshoot `masilia/ibexa-ai-craft`. It lives in [`skills/masilia-ibexa-ai-craft`](skills/masilia-ibexa-ai-craft/SKILL.md) and comes with the package.

```bash
# For the project (committed with it): from the installed package
mkdir -p .claude/skills && cp -r vendor/masilia/ibexa-ai-craft/skills/masilia-ibexa-ai-craft .claude/skills/

# For all your projects: global
mkdir -p ~/.claude/skills && cp -r vendor/masilia/ibexa-ai-craft/skills/masilia-ibexa-ai-craft ~/.claude/skills/
```

Restart the agent (or reload its skills) to pick it up. Update the copy after `composer update`.

## The Masilia packages

| Package | What it does |
| --- | --- |
| [`masilia/ibexa-admin-kit`](https://github.com/MasiliaLabs/ibexa-admin-kit) | React components in the design of the Ibexa admin, for admin pages |
| [`masilia/ibexa-sites`](https://github.com/MasiliaLabs/ibexa-sites) | A registry of sites that Ibexa serves without a deploy |
| [`masilia/ibexa-ai`](https://github.com/MasiliaLabs/ibexa-ai) | The AI assistant for Ibexa: capabilities, providers, chat, approval flow |
| [`masilia/ibexa-ai-craft`](https://github.com/MasiliaLabs/ibexa-ai-craft) | The assistant builds sites and pages out of blocks |
| [`masilia/ibexa-ai-editor`](https://github.com/MasiliaLabs/ibexa-ai-editor) | AI suggestions and image generation in the content editing form |
| [`masilia/ibexa-blocks`](https://github.com/MasiliaLabs/ibexa-blocks) | Front-office rendering of pages made of blocks, with themes |
| [`masilia/editorial-toolbox`](https://github.com/MasiliaLabs/editorial-toolbox) | In-context editing toolbar for the front office |

**What requires what** (Composer installs it for you):

- `ibexa-sites` requires `ibexa-admin-kit`
- `ibexa-ai` requires `ibexa-admin-kit`
- `ibexa-ai-editor` requires `ibexa-ai`
- `ibexa-ai-craft` requires `ibexa-ai`, `ibexa-blocks` and `ibexa-sites`
- `ibexa-blocks` and `editorial-toolbox` require none of the others; they are happy to find `ibexa-sites` (and each other) when installed.

## Compatibility

| Package version | PHP | Ibexa |
| --- | --- | --- |
| `latest` | 8.3+ | 5.0 |

## Download

- **Composer** (recommended): `composer require masilia/ibexa-ai-craft` — [ibexa-ai-craft on Packagist](https://packagist.org/packages/masilia/ibexa-ai-craft)
- **Archive** of a release: [GitHub releases](https://github.com/MasiliaLabs/ibexa-ai-craft/releases) (each release lists what changed)
- **Source**: `git clone https://github.com/MasiliaLabs/ibexa-ai-craft.git`
- What changed between versions: [CHANGELOG.md](CHANGELOG.md)

## Support, contributing and security

- Questions and bug reports: [GitHub issues](https://github.com/MasiliaLabs/ibexa-ai-craft/issues)
- Upgrading across a breaking change: [UPGRADE.md](UPGRADE.md)
- Pull requests are welcome, see [CONTRIBUTING.md](CONTRIBUTING.md)
- Security problems: please follow [SECURITY.md](SECURITY.md) and do not open a public issue

## License

Released under the [MIT license](LICENSE). Copyright © 2026 MasiliaLabs.

Ibexa itself is distributed under its own licenses (Ibexa OSS: GPL-2.0), which apply to the project that uses this package. This package is an independent project, not affiliated with or endorsed by Ibexa AS; "Ibexa" is a trademark of its owner.
