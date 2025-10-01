# Build & release guide

## Prerequisites

- PHP 8.2 (compatible with 8.0+) and the `php` binary available in `$PATH`.
- Composer 2.x.
- `zip`, `rsync`, and basic Unix utilities (`bash`, `find`).

## Local build commands

Run the build script from the repository root:

```bash
bash build.sh --bump=patch
```

This bumps the plugin patch version, installs production dependencies, and creates a clean ZIP in `build/`.

To set an explicit version instead of bumping automatically:

```bash
bash build.sh --set-version=1.2.3
```

Optional parameters:

- `--bump=minor` or `--bump=major` to change the bump strategy.
- `--zip-name=<custom-name.zip>` to override the generated archive name.

The script outputs the final version and the ZIP path.

## GitHub Action release

Tagging the repository with a version such as `v1.2.3` triggers the `Build plugin zip` workflow. The action:

1. Installs dependencies with Composer (production only).
2. Mirrors the plugin files into `build/fp-multilanguage/` with the same exclusions as the local script.
3. Generates a timestamped archive and uploads it as the `plugin-zip` artifact.

Download the artifact from the workflow run to publish the release package.
