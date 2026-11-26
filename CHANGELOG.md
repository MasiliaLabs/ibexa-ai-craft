# Changelog

All notable changes to `masilia/ibexa-ai-craft` are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [Semantic Versioning](https://semver.org/) (before 1.0, a minor version may contain breaking changes).

## [Unreleased]

## [2.3.3] - 2026-11-26

### Changed
- Works with `masilia/ibexa-ai` 3 and `masilia/ibexa-sites` 0.3 (their constraints are widened; no code changes).

## [2.3.2] - 2026-11-20

### Added
- Docs: a *Stay informed* section in the README: how to be told of new releases (GitHub Watch, the releases feed, `composer outdated`, Dependabot) and where to read what changes.

## [2.3.1] - 2026-11-20

### Fixed
- `site.create_structure` fills the `logo_text` field of the layout with the name of the site when the layout type has one: the socle of `masilia/ibexa-blocks` makes it required, and the build refused such a layout ("has required fields a new site cannot fill: logo_text").

## [2.3.0] - 2026-11-20

### Changed
- Accepts `masilia/ibexa-blocks` 0.6 (`^0.3 || … || ^0.6`). `site.create_structure` makes the folder of the blocks of the home and of the pages only when their content type is a container.
- The blocks of a page or of the home page are made in a folder under that page, named after it (`<page name> Blocks`, setting `conventions.blocks_folder_suffix`, shared with `masilia_ibexa_blocks`), found by the remote id `<page remote id>_blocks` and made when missing; before, they went in a folder called "<page name> items" that was found by its name. `site.create_structure` makes the folder of the home and of each page. `site.add_blocks` refuses a page that is not a container and says how to change it. The items of a block keep their own folder. Blocks that exist elsewhere are not moved.

## [2.2.3] - 2026-11-19

### Added
- Docs: a *With Symfony Flex* section in the README and in the skill: the Masilia recipes (`MasiliaLabs/recipes`) enable the bundle and write its route and config files on `composer require`.

## [2.2.2] - 2026-11-19

### Added
- An agent skill, `skills/masilia-ibexa-ai-craft`, for AI coding agents that work with the package; the README says how to install it.

## [2.2.1] - 2026-11-19

### Changed
- Accepts `masilia/ibexa-blocks` 0.5 (`^0.3 || ^0.4 || ^0.5`). No change of behaviour.

## [2.2.0] - 2026-11-17

### Added
- `items_field_by_type`: the field that holds the child items of the block types that do not call it `items` (for example `info_cards: cards`); blocks and the assistant read it.

### Changed
- Accepts `masilia/ibexa-blocks` `^0.3 || ^0.4` and `masilia/ibexa-sites` `^0.1 || ^0.2`, and declares the dependencies the code uses directly.
- Examples and test fixtures are neutral (no project names).

## [2.1.0] - 2026-11-15

### Added
- `site.config.*` capabilities (list, describe, create, update, set status, delete, default siteaccess) on `masilia/ibexa-sites`.

## [2.0.0] - 2026-11-14

### Changed
- **Breaking:** the implementation moves to `src/lib` and the bundle keeps the Symfony integration; requires `masilia/ibexa-ai` `^2.0`.

## [1.0.0] - 2026-11-14

### Added
- Page composition, themes, lists, newsletter and item kinds, on the contracts of `masilia/ibexa-blocks` 0.3.

### Changed
- **Breaking:** follows the 1.0 AI OS (tools are capabilities); `site.create_structure` is validated and runs in one transaction with the conventions' types.

## [0.1.0] - 2026-11-13

First release: how Masilia sites are built, for the Ibexa AI OS.

### Added
- `site.conventions`: how the pages of a site are built, mined from the pages themselves.
- A self-test scenario for the page-of-blocks flow.

[Unreleased]: https://github.com/MasiliaLabs/ibexa-ai-craft/compare/2.3.3...HEAD
[2.3.3]: https://github.com/MasiliaLabs/ibexa-ai-craft/compare/2.3.2...2.3.3
[2.3.2]: https://github.com/MasiliaLabs/ibexa-ai-craft/compare/2.3.1...2.3.2
[2.3.1]: https://github.com/MasiliaLabs/ibexa-ai-craft/compare/2.3.0...2.3.1
[2.3.0]: https://github.com/MasiliaLabs/ibexa-ai-craft/compare/2.2.3...2.3.0
[2.2.3]: https://github.com/MasiliaLabs/ibexa-ai-craft/compare/2.2.2...2.2.3
[2.2.2]: https://github.com/MasiliaLabs/ibexa-ai-craft/compare/2.2.1...2.2.2
[2.2.1]: https://github.com/MasiliaLabs/ibexa-ai-craft/compare/2.2.0...2.2.1
[2.2.0]: https://github.com/MasiliaLabs/ibexa-ai-craft/compare/2.1.0...2.2.0
[2.1.0]: https://github.com/MasiliaLabs/ibexa-ai-craft/compare/2.0.0...2.1.0
[2.0.0]: https://github.com/MasiliaLabs/ibexa-ai-craft/compare/1.0.0...2.0.0
[1.0.0]: https://github.com/MasiliaLabs/ibexa-ai-craft/compare/0.1.0...1.0.0
[0.1.0]: https://github.com/MasiliaLabs/ibexa-ai-craft/releases/tag/0.1.0
