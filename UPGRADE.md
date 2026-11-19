# Upgrade guide

What to change in your project when you move to a version that breaks something. The full list of changes is in the [CHANGELOG](CHANGELOG.md).

## From 1.x to 2.0

- Requires `masilia/ibexa-ai` `^2.0` (see its [UPGRADE.md](https://github.com/MasiliaLabs/ibexa-ai/blob/master/UPGRADE.md)).
- **The implementation moved to `src/lib`; the bundle keeps the Symfony integration.** Classes you referenced in `Masilia\Bundle\IbexaAiCraft` for logic (not wiring) now live in `Masilia\IbexaAiCraft`. Update your `use` statements and service definitions.

## From 0.1 to 1.0

- Page composition, themes, lists, newsletter and item kinds run on the contracts of `masilia/ibexa-blocks` 0.3, and follow the 1.0 AI OS (tools are capabilities). `site.create_structure` is validated and runs in one transaction with the types of the conventions.
