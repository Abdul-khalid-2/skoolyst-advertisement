<?php

/**
 * Autoload
 *
 * Minimal, dependency-free class autoloader (Section 9/11). No
 * composer.json/vendor exists yet (Section 11.e/f are still
 * unstarted), so this avoids requiring every controller/repository
 * by hand in every entry point — the same reasoning as core/Env.php.
 * Once Composer is set up, this can be swapped for
 * vendor/autoload.php's generated PSR-4 loader with no changes to
 * calling code, since the namespace/directory mapping is identical
 * (App\ => app/, Core\ => core/).
 *
 * Usage (top of any entry point, before any App\ or Core\ class is
 * referenced):
 *   require __DIR__ . '/../core/Autoload.php';
 */

spl_autoload_register(function (string $class): void {
    $prefixes = [
        'App\\' => __DIR__ . '/../app/',
        'Core\\' => __DIR__ . '/',
    ];

    foreach ($prefixes as $prefix => $baseDir) {
        if (!str_starts_with($class, $prefix)) {
            continue;
        }

        $relative = substr($class, strlen($prefix));
        $path = $baseDir . str_replace('\\', '/', $relative) . '.php';

        if (is_file($path)) {
            require $path;
        }

        return;
    }
});
