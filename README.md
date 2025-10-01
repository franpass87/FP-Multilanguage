# FP-Multilanguage

FP Multilanguage is a custom WordPress plugin that handles multilingual content management.

## Release process

See [README-BUILD.md](README-BUILD.md) for detailed build prerequisites and commands. In summary:

1. Update the plugin version if needed using the build script:
   - `bash build.sh --bump=patch`
   - or `bash build.sh --set-version=1.2.3`
2. Upload the generated ZIP file from the `build/` directory to your WordPress installation or release it on GitHub.
3. Tag the release as `vX.Y.Z` to trigger the automated GitHub Action that attaches the ZIP artifact to the workflow run.
