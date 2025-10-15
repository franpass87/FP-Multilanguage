#!/usr/bin/env php
<?php
declare(strict_types=1);

const AUTHOR_NAME = 'Francesco Passeri';
const AUTHOR_EMAIL = 'info@francescopasseri.com';
const AUTHOR_URI = 'https://francescopasseri.com';
const PLUGIN_URI = 'https://francescopasseri.com';
const SHORT_DESCRIPTION = 'Automates Italian-to-English copies of content, taxonomies, menus, media, and SEO data with queue-based routing and trusted translation providers.';

$argvCopy = $argv;
array_shift($argvCopy);
$apply = false;
$docsMode = false;

foreach ($argvCopy as $arg) {
    if (str_starts_with($arg, '--apply')) {
        $parts = explode('=', $arg, 2);
        $apply = isset($parts[1]) ? filter_var($parts[1], FILTER_VALIDATE_BOOLEAN) : true;
    }
    if ('--docs' === $arg || str_starts_with($arg, '--docs=')) {
        $docsMode = true;
    }
}

$report = [];

function updateFile(string $path, callable $callback, bool $apply, array &$report): void {
    if (!file_exists($path)) {
        return;
    }

    $original = file_get_contents($path);
    $updated = $callback($original);

    if (!is_string($updated)) {
        return;
    }

    if ($updated === $original) {
        return;
    }

    if ($apply) {
        $backup = $path . '.bak';
        if (!file_exists($backup)) {
            file_put_contents($backup, $original);
        }
        file_put_contents($path, $updated);
    }

    $report[$path] = $report[$path] ?? [];
}

function registerChange(array &$report, string $path, string $field): void {
    $report[$path] = $report[$path] ?? [];
    if (!in_array($field, $report[$path], true)) {
        $report[$path][] = $field;
    }
}

// Update plugin header.
updateFile('fp-multilanguage/fp-multilanguage.php', function (string $content) use (&$report) {
    $updated = $content;
    $updated = preg_replace('/^ \* Author:.*$/m', ' * Author: ' . AUTHOR_NAME, $updated, -1, $authorCount);
    if ($authorCount > 0) {
        registerChange($report, 'fp-multilanguage/fp-multilanguage.php', 'Author');
    }
    if (!preg_match('/^ \* Plugin URI:/m', $updated)) {
        $updated = preg_replace('/^ \* Plugin Name:.*$/m', " * Plugin Name: FP Multilanguage\n * Plugin URI: " . PLUGIN_URI, $updated, 1, $uriCount);
        if ($uriCount > 0) {
            registerChange($report, 'fp-multilanguage/fp-multilanguage.php', 'Plugin URI (added)');
        }
    } else {
        $updated = preg_replace('/^ \* Plugin URI:.*$/m', ' * Plugin URI: ' . PLUGIN_URI, $updated, -1, $uriCount);
        if ($uriCount > 0) {
            registerChange($report, 'fp-multilanguage/fp-multilanguage.php', 'Plugin URI');
        }
    }
    $updated = preg_replace('/^ \* Author URI:.*$/m', ' * Author URI: ' . AUTHOR_URI, $updated, -1, $authorUriCount);
    if ($authorUriCount > 0) {
        registerChange($report, 'fp-multilanguage/fp-multilanguage.php', 'Author URI');
    }
    $updated = preg_replace('/^ \* Description:.*$/m', ' * Description: ' . SHORT_DESCRIPTION, $updated, -1, $descCount);
    if ($descCount > 0) {
        registerChange($report, 'fp-multilanguage/fp-multilanguage.php', 'Description');
    }
    $updated = preg_replace('/\* @package FP_Multilanguage(?!\n \* @author)/', "* @package FP_Multilanguage\n * @author " . AUTHOR_NAME, $updated, 1, $pkgCount);
    if ($pkgCount > 0) {
        registerChange($report, 'fp-multilanguage/fp-multilanguage.php', 'Docblock author');
    }
    $updated = preg_replace('/\* @author ' . preg_quote(AUTHOR_NAME, '/') . '(?!\n \* @link)/', "* @author " . AUTHOR_NAME . "\n * @link " . AUTHOR_URI, $updated, 1, $linkCount);
    if ($linkCount > 0) {
        registerChange($report, 'fp-multilanguage/fp-multilanguage.php', 'Docblock link');
    }
    return $updated;
}, $apply, $report);

$phpTargets = [
    'fp-multilanguage/admin/class-admin.php',
    'fp-multilanguage/cli/class-cli.php',
    'fp-multilanguage/includes/class-content-diff.php',
    'fp-multilanguage/includes/class-export-import.php',
    'fp-multilanguage/includes/class-glossary.php',
    'fp-multilanguage/includes/class-language.php',
    'fp-multilanguage/includes/class-logger.php',
    'fp-multilanguage/includes/class-media-front.php',
    'fp-multilanguage/includes/class-menu-sync.php',
    'fp-multilanguage/includes/class-plugin.php',
    'fp-multilanguage/includes/class-processor.php',
    'fp-multilanguage/includes/class-queue.php',
    'fp-multilanguage/includes/class-rewrites.php',
    'fp-multilanguage/includes/class-seo.php',
    'fp-multilanguage/includes/class-settings.php',
    'fp-multilanguage/includes/class-strings-override.php',
    'fp-multilanguage/includes/class-strings-scanner.php',
    'fp-multilanguage/includes/providers/class-provider-google.php',
    'fp-multilanguage/includes/providers/class-provider-openai.php',
    'fp-multilanguage/includes/providers/interface-translator.php',
    'fp-multilanguage/rest/class-rest-admin.php',
    'fp-multilanguage/uninstall.php',
];

foreach ($phpTargets as $phpFile) {
    updateFile($phpFile, function (string $content) use ($phpFile, &$report) {
        $pattern = '/\* @package FP_Multilanguage(?!\n \* @author)/';
        $updated = preg_replace($pattern, "* @package FP_Multilanguage\n * @author " . AUTHOR_NAME, $content, 1, $count);
        if ($count > 0) {
            registerChange($report, $phpFile, 'Docblock author');
        }
        $patternLink = '/\* @author ' . preg_quote(AUTHOR_NAME, '/') . '(?!\n \* @link)/';
        $updated = preg_replace($patternLink, "* @author " . AUTHOR_NAME . "\n * @link " . AUTHOR_URI, $updated, 1, $linkCount);
        if ($linkCount > 0) {
            registerChange($report, $phpFile, 'Docblock link');
        }
        if ($updated === $content && !str_contains($content, '@author ' . AUTHOR_NAME)) {
            $updated = preg_replace('/\* @package FP_Multilanguage\n/', "* @package FP_Multilanguage\n * @author " . AUTHOR_NAME . "\n * @link " . AUTHOR_URI . "\n", $content, 1, $fullCount);
            if ($fullCount > 0) {
                registerChange($report, $phpFile, 'Docblock author/link');
            }
        }
        return $updated;
    }, $apply, $report);
}

// Update readme.txt metadata (always ensures sync).
updateFile('fp-multilanguage/readme.txt', function (string $content) use (&$report) {
        $updated = preg_replace('/^Contributors:.*/m', 'Contributors: francescopasseri', $content, -1, $contribCount);
        if ($contribCount > 0) {
            registerChange($report, 'fp-multilanguage/readme.txt', 'Contributors');
        }
        $updated = preg_replace('/^Stable tag:.*/m', 'Stable tag: 0.3.1', $updated, -1, $stableCount);
        if ($stableCount > 0) {
            registerChange($report, 'fp-multilanguage/readme.txt', 'Stable tag');
        }
        if (!preg_match('/^Plugin Homepage:/m', $updated)) {
            $updated = preg_replace('/^License URI:.*$/m', "License URI: https://www.gnu.org/licenses/gpl-2.0.html\nPlugin Homepage: " . PLUGIN_URI, $updated, 1, $homepageCount);
            if ($homepageCount > 0) {
                registerChange($report, 'fp-multilanguage/readme.txt', 'Plugin Homepage');
            }
        } else {
            $updated = preg_replace('/^Plugin Homepage:.*/m', 'Plugin Homepage: ' . PLUGIN_URI, $updated, -1, $homepageCount);
            if ($homepageCount > 0) {
                registerChange($report, 'fp-multilanguage/readme.txt', 'Plugin Homepage');
            }
        }
        $updated = preg_replace('/^Requires at least:.*/m', 'Requires at least: 5.8', $updated);
        $updated = preg_replace('/^Tested up to:.*/m', 'Tested up to: 6.5', $updated);
        $updated = preg_replace('/^Requires PHP:.*/m', 'Requires PHP: 7.4', $updated);
        $updated = preg_replace('/^Automates .*$/m', SHORT_DESCRIPTION, $updated, 1, $descCount);
        if ($descCount > 0) {
            registerChange($report, 'fp-multilanguage/readme.txt', 'Short description');
        }
        return $updated;
}, $apply, $report);

// Update README.md table values.
updateFile('README.md', function (string $content) use (&$report) {
        $updated = $content;
        $updated = preg_replace('/> .*\n/', '> ' . SHORT_DESCRIPTION . "\n", $updated, 1, $quoteCount);
        if ($quoteCount > 0) {
            registerChange($report, 'README.md', 'Intro description');
        }
        $map = [
            '| **Version** |' => '| **Version** | 0.3.1 |',
            '| **Author** |' => '| **Author** | [Francesco Passeri](https://francescopasseri.com) |',
            '| **Author Email** |' => '| **Author Email** | [info@francescopasseri.com](mailto:info@francescopasseri.com) |',
            '| **Author URI** |' => '| **Author URI** | https://francescopasseri.com |',
            '| **Plugin Homepage** |' => '| **Plugin Homepage** | https://francescopasseri.com |'
        ];
        foreach ($map as $needle => $replacement) {
            $pattern = '/' . preg_quote($needle, '/') . '.*\|/';
            $updated = preg_replace($pattern, $replacement, $updated, 1, $count);
            if ($count > 0) {
                registerChange($report, 'README.md', trim($needle, '| *'));
            }
        }
        return $updated;
}, $apply, $report);

// Update composer.json
updateFile('composer.json', function (string $content) use (&$report) {
    $data = json_decode($content, true);
    if (!is_array($data)) {
        return $content;
    }
    $changed = false;
    $fields = ['description' => SHORT_DESCRIPTION, 'homepage' => PLUGIN_URI];
    foreach ($fields as $key => $value) {
        if (($data[$key] ?? null) !== $value) {
            $data[$key] = $value;
            $changed = true;
            registerChange($report, 'composer.json', ucfirst($key));
        }
    }
    $support = [
        'issues' => 'https://github.com/francescopasseri/FP-Multilanguage/issues',
        'source' => 'https://github.com/francescopasseri/FP-Multilanguage',
    ];
    if (($data['support'] ?? null) !== $support) {
        $data['support'] = $support;
        $changed = true;
        registerChange($report, 'composer.json', 'Support');
    }
    $authors = [
        [
            'name' => AUTHOR_NAME,
            'email' => AUTHOR_EMAIL,
            'homepage' => AUTHOR_URI,
            'role' => 'Developer',
        ],
    ];
    if (($data['authors'] ?? null) !== $authors) {
        $data['authors'] = $authors;
        $changed = true;
        registerChange($report, 'composer.json', 'Authors');
    }
    $scripts = $data['scripts'] ?? [];
    $expectedScripts = [
        'sync:author' => '@php tools/sync-author-metadata.php --apply=${APPLY:-false}',
        'sync:docs' => '@php tools/sync-author-metadata.php --docs --apply=${APPLY:-false}',
        'changelog:from-git' => 'npm run changelog:from-git',
    ];
    foreach ($expectedScripts as $name => $command) {
        $target = [$command];
        if (($scripts[$name] ?? null) !== $target) {
            $scripts[$name] = $target;
            $changed = true;
            registerChange($report, 'composer.json', 'Script ' . $name);
        }
    }
    $data['scripts'] = $scripts;
    return $changed ? json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n" : $content;
}, $apply, $report);

// Update package.json if available.
updateFile('package.json', function (string $content) use (&$report) {
    $data = json_decode($content, true);
    if (!is_array($data)) {
        $data = [];
    }
    $changed = false;
    $defaults = [
        'name' => 'fp-multilanguage',
        'version' => '0.3.1',
        'description' => SHORT_DESCRIPTION,
        'author' => AUTHOR_NAME . ' <' . AUTHOR_EMAIL . '> (' . AUTHOR_URI . ')',
        'homepage' => PLUGIN_URI,
    ];
    foreach ($defaults as $key => $value) {
        if (($data[$key] ?? null) !== $value) {
            $data[$key] = $value;
            $changed = true;
            registerChange($report, 'package.json', ucfirst($key));
        }
    }
    $bugs = [
        'url' => 'https://github.com/francescopasseri/FP-Multilanguage/issues',
    ];
    if (($data['bugs'] ?? null) !== $bugs) {
        $data['bugs'] = $bugs;
        $changed = true;
        registerChange($report, 'package.json', 'Bugs URL');
    }
    $scripts = $data['scripts'] ?? [];
    $expectedScripts = [
        'sync:author' => 'php tools/sync-author-metadata.php --apply=${APPLY:-false}',
        'sync:docs' => 'php tools/sync-author-metadata.php --docs --apply=${APPLY:-false}',
        'changelog:from-git' => 'conventional-changelog -p angular -i CHANGELOG.md -s || true',
    ];
    foreach ($expectedScripts as $key => $value) {
        if (($scripts[$key] ?? null) !== $value) {
            $scripts[$key] = $value;
            $changed = true;
            registerChange($report, 'package.json', 'Script ' . $key);
        }
    }
    $devDeps = $data['devDependencies'] ?? [];
    if (($devDeps['conventional-changelog-cli'] ?? null) !== '^3.0.0') {
        $devDeps['conventional-changelog-cli'] = '^3.0.0';
        $data['devDependencies'] = $devDeps;
        $changed = true;
        registerChange($report, 'package.json', 'DevDependency conventional-changelog-cli');
    }
    return $changed ? json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n" : $content;
}, $apply, $report);

if ($docsMode) {
    updateFile('docs/overview.md', function (string $content) use (&$report) {
        $updated = preg_replace('/^# FP Multilanguage Overview\n\n.*\n/', "# FP Multilanguage Overview\n\n" . SHORT_DESCRIPTION . "\n", $content, 1, $count);
        if ($count > 0) {
            registerChange($report, 'docs/overview.md', 'Intro description');
        }
        return $updated;
    }, $apply, $report);
}

if (empty($report)) {
    fwrite(STDOUT, "No changes required.\n");
    exit(0);
}

$maxFile = max(array_map('strlen', array_keys($report)));
$line = str_repeat('-', $maxFile + 30);
fwrite(STDOUT, $line . "\n");
fwrite(STDOUT, str_pad('File', $maxFile + 2) . "Updated Fields\n");
fwrite(STDOUT, $line . "\n");
foreach ($report as $file => $fields) {
    fwrite(STDOUT, str_pad($file, $maxFile + 2) . implode(', ', $fields) . "\n");
}
fwrite(STDOUT, $line . "\n");

exit($apply ? 0 : 0);
