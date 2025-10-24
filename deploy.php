<?php
namespace Deployer;

require 'recipe/common.php';
require 'recipe/composer.php';

// --------------------------------------
// Projekt-Konfiguration
// --------------------------------------
set('application', 'my-profil');
set('repository', 'git@github.com:maidem/my-profil.git');
set('branch', function () {
    return getenv('DEPLOY_BRANCH') ?: 'main';
});

// PHP-Binary & Composer-Optionen
set('bin/php', '/usr/bin/php');
set('composer_options', '--no-dev --optimize-autoloader');
set('keep_releases', 5);
set('allow_anonymous_stats', false);

// --------------------------------------
// TYPO3-spezifische Verzeichnisse
// --------------------------------------
set('shared_dirs', [
    'var',
    'public/fileadmin',
    'public/uploads',
    'public/typo3temp',
]);

set('shared_files', [
    '.env',
    'config/system/additional.php',
]);

set('writable_dirs', [
    'var',
    'public/fileadmin',
    'public/uploads',
    'public/typo3temp',
]);

// --------------------------------------
// Server-Definition aus GitHub Secrets
// --------------------------------------
host('live')
    ->set('hostname', getenv('DEPLOY_HOST'))
    ->set('remote_user', getenv('DEPLOY_SSH_USER'))
    ->set('deploy_path', getenv('DEPLOY_PATH'))
    ->set('forward_agent', true)
    ->set('writable_mode', 'chmod')
    ->set('writable_use_sudo', false)
    ->set('multiplexing', false);

// --------------------------------------
// Haupt-Task für Deployment
// --------------------------------------
desc('Deploy TYPO3 Projekt');
task('deploy', [
    'deploy:prepare',
    'deploy:release',
    'deploy:update_code',
    'deploy:shared',
    'deploy:vendors',      // führt composer install aus
    'deploy:writable',
    'deploy:symlink',
    'deploy:cleanup',
]);

// --------------------------------------
// Hooks & Extras
// --------------------------------------
after('deploy:failed', 'deploy:unlock');

// TYPO3 Cache leeren nach Deployment
desc('Flush TYPO3 cache');
task('typo3:cache:flush', function () {
    run('cd {{current_path}} && {{bin/php}} vendor/bin/typo3 cache:flush || true');
});
after('deploy:symlink', 'typo3:cache:flush');

// Besitzerrechte korrigieren
task('fix:permissions', function () {
    run('sudo chown -R www-data:www-data {{deploy_path}}');
});
after('deploy:symlink', 'fix:permissions');