# Frequently Asked Questions

## Does FP Multilanguage overwrite manual edits on English content?
Only sections modified on the Italian source are retranslated. Manual adjustments on the English copy remain untouched unless the original segment changes again.

## How can I limit translations to specific post types or taxonomies?
Use the settings UI toggles or hook into `fpml_translatable_post_types` / `fpml_translatable_taxonomies` to add or remove objects before jobs are enqueued.

## Can I translate custom fields created by Advanced Custom Fields (ACF)?
Yes. The processor parses nested ACF structures, repeater fields, and flexible content while preserving array structure and only translating textual values.

## What happens if a provider API is unavailable?
Jobs remain in an `error` state with the provider error message stored in `last_error`. The processor retries according to queue settings, and diagnostics highlights failing providers for quick troubleshooting.

## How do I estimate translation costs before running the queue?
Use `wp fpml queue estimate-cost --states=pending --max-jobs=100` or the Diagnostics dashboard. Providers expose pricing profiles so estimates reflect your configured rates.

## Is there a way to purge old queue entries?
Enable retention inside the settings or run `wp fpml queue cleanup --days=<int>`; filters `fpml_queue_cleanup_states` and `fpml_queue_cleanup_batch_size` allow deeper customization.

## Does the plugin work alongside WPML or Polylang?
When either is active, FP Multilanguage switches to assisted mode: automatic duplication, routing, and queue processing are paused, but provider tools, glossary, overrides, and manual exports remain available.

## How are media assets handled during translation?
The plugin reuses the original attachment files and only translates textual metadata (title, caption, ALT). On the English frontend it swaps attachment IDs in shortcodes/builders to avoid mismatched language assets.

## Can I trigger translations via REST?
Yes. Authenticated administrators can use the REST endpoints exposed under `fpml/v1` (`/queue/run`, `/queue/cleanup`, `/test-provider`, `/reindex`) using a nonce-protected request.

## What maintenance tasks should be scheduled?
Schedule regular queue runs (`wp fpml queue run`) and, if retention is active, cleanup (`wp fpml queue cleanup`). Monitor Diagnostics for job age, retention status, and provider health.
