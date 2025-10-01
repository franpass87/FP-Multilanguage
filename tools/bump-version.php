<?php
declare(strict_types=1);

function usage(): void {
    fwrite(STDERR, "Usage: php tools/bump-version.php [--set=X.Y.Z|--bump=patch|minor|major|--patch|--minor|--major]\n");
}

$options = getopt('', ['set:', 'bump:', 'patch', 'minor', 'major']);

$setVersion = $options['set'] ?? null;
$bumpOption = $options['bump'] ?? null;

if (isset($options['patch'])) {
    $bumpOption = 'patch';
} elseif (isset($options['minor'])) {
    $bumpOption = 'minor';
} elseif (isset($options['major'])) {
    $bumpOption = 'major';
}

if (null !== $setVersion && null !== $bumpOption) {
    fwrite(STDERR, "You cannot use --set together with bump options.\n");
    exit(1);
}

$rootDir = dirname(__DIR__);
$slug = 'fp-multilanguage';
$mainFile = $rootDir . DIRECTORY_SEPARATOR . $slug . DIRECTORY_SEPARATOR . 'fp-multilanguage.php';

if (!is_file($mainFile) || !is_readable($mainFile)) {
    fwrite(STDERR, "Unable to read plugin main file at {$mainFile}.\n");
    exit(1);
}

$contents = file_get_contents($mainFile);

if (false === $contents) {
    fwrite(STDERR, "Failed to load plugin file contents.\n");
    exit(1);
}

$bom = '';
if (0 === strncmp($contents, "\xEF\xBB\xBF", 3)) {
    $bom = "\xEF\xBB\xBF";
    $contents = substr($contents, 3);
}

if (!preg_match('/^\s*\*\s*Version:\s*(.+)$/mi', $contents, $matches)) {
    fwrite(STDERR, "Version header not found.\n");
    exit(1);
}

$currentVersion = trim($matches[1]);
$newVersion = $currentVersion;

if (null !== $setVersion) {
    if (!preg_match('/^\d+\.\d+\.\d+$/', $setVersion)) {
        fwrite(STDERR, "Invalid version supplied for --set. Use semantic versioning (e.g. 1.2.3).\n");
        exit(1);
    }
    $newVersion = $setVersion;
} else {
    $bumpType = $bumpOption ?: 'patch';
    if (!in_array($bumpType, ['patch', 'minor', 'major'], true)) {
        fwrite(STDERR, "Unknown bump type '{$bumpType}'.\n");
        exit(1);
    }

    $parts = explode('.', $currentVersion);
    if (count($parts) < 3) {
        fwrite(STDERR, "Current version '{$currentVersion}' is not semantic (X.Y.Z).\n");
        exit(1);
    }

    [$major, $minor, $patch] = array_map('intval', array_slice($parts, 0, 3));

    switch ($bumpType) {
        case 'major':
            $major++;
            $minor = 0;
            $patch = 0;
            break;
        case 'minor':
            $minor++;
            $patch = 0;
            break;
        default:
            $patch++;
            break;
    }

    $newVersion = sprintf('%d.%d.%d', $major, $minor, $patch);
}

if ($newVersion === $currentVersion) {
    fwrite(STDOUT, $newVersion . PHP_EOL);
    exit(0);
}

$updated = $contents;
$updated = preg_replace('/(^\s*\*\s*Version:\s*)(.+)$/mi', '${1}' . $newVersion, $updated, 1);

if (null === $updated) {
    fwrite(STDERR, "Failed to update version header.\n");
    exit(1);
}

$updated = preg_replace("/define\(\s*'FPML_PLUGIN_VERSION'\s*,\s*'[^']*'\s*\);/", "define( 'FPML_PLUGIN_VERSION', '{$newVersion}' );", $updated, 1);

if (null === $updated) {
    fwrite(STDERR, "Failed to update FPML_PLUGIN_VERSION constant.\n");
    exit(1);
}

if (file_put_contents($mainFile, $bom . $updated) === false) {
    fwrite(STDERR, "Failed to write updated version to plugin file.\n");
    exit(1);
}

fwrite(STDOUT, $newVersion . PHP_EOL);
