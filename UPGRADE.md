# Upgrade guide

What to change in your project when you move to a version that breaks something. The full list of changes is in the [CHANGELOG](CHANGELOG.md).

## From 2.x to 3.0

- Requires `masilia/ibexa-blocks` `^0.8` (see its [UPGRADE.md](https://github.com/MasiliaLabs/ibexa-blocks/blob/main/UPGRADE.md): every setting is per site, under `system`). Update it first, and move its settings under `system.default` as that guide says.
- **The conventions are those of the site of the request.** `Support\Conventions` is no longer a service built at compilation: classes that need them take `Masilia\IbexaAiCraft\Support\ConventionsSource` (the bundle gives `DependencyInjection\BlocksConventions`, which reads those of `masilia/ibexa-blocks` for the site, or the settings of this bundle) and ask it `conventions()` each time. A class of yours that was given `Conventions` keeps working with a `Conventions` object (it is its own source). The compiler pass `SharedConventionsPass` and the `masilia_blocks.conventions` parameter it read are gone.
- `newsletter.subscribers.list` has an optional `list` and its rows say their list (the subscribers are per list in `masilia/ibexa-blocks` 0.8): all the lists by default, as before.

## From 1.x to 2.0

- Requires `masilia/ibexa-ai` `^2.0` (see its [UPGRADE.md](https://github.com/MasiliaLabs/ibexa-ai/blob/master/UPGRADE.md)).
- **The implementation moved to `src/lib`; the bundle keeps the Symfony integration.** Classes you referenced in `Masilia\Bundle\IbexaAiCraft` for logic (not wiring) now live in `Masilia\IbexaAiCraft`. Update your `use` statements and service definitions.

## From 0.1 to 1.0

- Page composition, themes, lists, newsletter and item kinds run on the contracts of `masilia/ibexa-blocks` 0.3, and follow the 1.0 AI OS (tools are capabilities). `site.create_structure` is validated and runs in one transaction with the types of the conventions.
