<?php
namespace Deployer;

require 'recipe/common.php';

// ---------------------------------------------------------
// Projekt-Konfiguration
// ---------------------------------------------------------
set('application', 'my-profil');
set('repository', 'git@github.com:maidem/my-profil.git');
set('branch', function () {
    return getenv('DEPLOY_BRANCH') ?: 'main';
});
set('bin/php', '/usr/bin/php8.3');
set('ssh_private_key', getenv('DEPLOY_SSH_KEY'));

set('allow_anonymous_stats', false);
set('keep_releases', 5);

// ---------------------------------------------------------
// TYPO3 spezifische shared & writable Verzeichnisse
// ---------------------------------------------------------
set('shared_dirs', [
    'public/fileadmin',
    'public/uploads',
    'public/typo3temp',
    'var',
    'config/sites',
]);

set('shared_files', [
    'config/system/additional.php',
    'public/.htaccess',
    'public/.user.ini',
]);

set('writable_dirs', [
    'var',
    'public/fileadmin',
    'public/uploads',
]);

// ---------------------------------------------------------
// Host-Konfiguration
// ---------------------------------------------------------
host('live')
    ->set('hostname', getenv('DEPLOY_HOST') ?: 'example.com')
    ->set('remote_user', getenv('DEPLOY_SSH_USER') ?: 'deployer')
    ->set('deploy_path', getenv('DEPLOY_PATH') ?: '/var/www/html');

// ---------------------------------------------------------
// TYPO3 Cache leeren
// ---------------------------------------------------------
desc('Flush TYPO3 cache');
task('typo3:cache:flush', function () {
    run('{{bin/php}} {{current_path}}/vendor/bin/typo3 cache:flush || true');
});

// ---------------------------------------------------------
// Dateiberechtigungen setzen (mit Fehler-Toleranz)
// ---------------------------------------------------------
desc('Set correct permissions');
task('fix:permissions', function () {
    // Rechte im aktuellen Release setzen
    run('find {{release_path}} -type d -exec chmod 2770 {} + || true');
    run('find {{release_path}} -type f -exec chmod 0660 {} + || true');

    // Shared-Ordner prüfen und Rechte anpassen
    $sharedDirs = [
        '{{deploy_path}}/shared/public/fileadmin',
        '{{deploy_path}}/shared/public/uploads',
        '{{deploy_path}}/shared/public/typo3temp',
        '{{deploy_path}}/shared/var',
    ];

    foreach ($sharedDirs as $dir) {
        run("if [ -d $dir ]; then find $dir -type d -exec chmod 2770 {} + || true; find $dir -type f -exec chmod 0660 {} + || true; fi");
    }

    writeln('<info>Permissions fixed (non-critical chmod errors ignored).</info>');
});

// ---------------------------------------------------------
// Hooks (Reihenfolge der Tasks)
// ---------------------------------------------------------
after('deploy:prepare', 'fix:permissions');
after('deploy:update_code', 'deploy:vendors');
after('deploy:symlink', 'typo3:cache:flush');
after('deploy:symlink', 'fix:permissions');
after('deploy:success', 'fix:permissions');
after('deploy:failed', 'deploy:unlock');

// ---------------------------------------------------------
// Rollback Task
// ---------------------------------------------------------
desc('Rollback to previous release');
task('rollback', function () {
    run('cd {{deploy_path}} && ln -nfs $(ls -td releases/* | sed -n 2p) current');
    invoke('fix:permissions');
    invoke('typo3:cache:flush');
});

// ---------------------------------------------------------
// Hinweis für Build
// ---------------------------------------------------------
// Der Vite-Build wird automatisch in GitHub Actions ausgeführt.
// Auf dem Server ist kein Node.js oder npm nötig.