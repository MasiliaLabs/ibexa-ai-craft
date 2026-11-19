# Changelog

All notable changes to `masilia/ibexa-ai-craft` are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [Semantic Versioning](https://semver.org/) (before 1.0, a minor version may contain breaking changes).

## [Unreleased]

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

[Unreleased]: https://github.com/MasiliaLabs/ibexa-ai-craft/compare/2.2.3...HEAD
[2.2.3]: https://github.com/MasiliaLabs/ibexa-ai-craft/compare/2.2.2...2.2.3
[2.2.2]: https://github.com/MasiliaLabs/ibexa-ai-craft/compare/2.2.1...2.2.2
[2.2.1]: https://github.com/MasiliaLabs/ibexa-ai-craft/compare/2.2.0...2.2.1
[2.2.0]: https://github.com/MasiliaLabs/ibexa-ai-craft/compare/2.1.0...2.2.0
[2.1.0]: https://github.com/MasiliaLabs/ibexa-ai-craft/compare/2.0.0...2.1.0
[2.0.0]: https://github.com/MasiliaLabs/ibexa-ai-craft/compare/1.0.0...2.0.0
[1.0.0]: https://github.com/MasiliaLabs/ibexa-ai-craft/compare/0.1.0...1.0.0
[0.1.0]: https://github.com/MasiliaLabs/ibexa-ai-craft/releases/tag/0.1.0
