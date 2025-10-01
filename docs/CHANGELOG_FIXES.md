# Fix Changelog

| ID | File | Line | Severity | Fix summary | Commit |
| --- | --- | --- | --- | --- | --- |
| ISSUE-002 | fp-multilanguage/includes/class-settings.php | 66 | High | Flush rewrite rules after routing mode changes to refresh /en/ routes | fix(functional): flush rewrites on routing change (ISSUE-002) |
| ISSUE-001 | fp-multilanguage/includes/class-plugin.php | 262 | High | Disable autoload for translation catalogs and migrate existing installs | fix(performance): disable autoload for translation catalogs (ISSUE-001) |
| ISSUE-004 | fp-multilanguage/includes/class-strings-override.php | 138 | Medium | Preserve safe HTML in overrides with wp_kses_post sanitization | fix(functional): allow HTML in overrides (ISSUE-004) |
| ISSUE-003 | fp-multilanguage/includes/class-export-import.php | 664 | Medium | Stream CSV parsing to preserve quoted newlines during import | fix(functional): stream CSV parsing for imports (ISSUE-003) |
