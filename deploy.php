<?php
namespace Deployer;

require 'recipe/common.php';

// Projekt-Konfiguration
set('application', 'my-profil');
set('repository', 'git@github.com:maidem/my-profil.git');
set('branch', function () {
    return getenv('DEPLOY_BRANCH') ?: 'main';
});
set('bin/php', '/usr/bin/php8.3');
set('ssh_private_key', getenv('DEPLOY_SSH_KEY'));

// TYPO3-spezifische Verzeichnisse
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

set('allow_anonymous_stats', false);
set('keep_releases', 5);

// Host-Konfiguration mit Fallbacks
host('live')
    ->set('hostname', getenv('DEPLOY_HOST') ?: 'example.com')
    ->set('remote_user', getenv('DEPLOY_SSH_USER') ?: 'deployer')
    ->set('deploy_path', getenv('DEPLOY_PATH') ?: '/var/www/html');

// TYPO3 Cache leeren
desc('Flush TYPO3 cache');
task('typo3:cache:flush', function () {
    run('{{bin/php}} {{current_path}}/vendor/bin/typo3 cache:flush || true');
});

// Rechte setzen
desc('Set correct permissions');
task('fix:permissions', function () {
    run('find {{release_path}} -type d -exec chmod 2770 {} +');
    run('find {{release_path}} -type f -exec chmod 0660 {} +');

    $sharedDirs = [
        '{{deploy_path}}/shared/public/fileadmin',
        '{{deploy_path}}/shared/public/uploads',
        '{{deploy_path}}/shared/public/typo3temp',
        '{{deploy_path}}/shared/var',
    ];

    foreach ($sharedDirs as $dir) {
        run("if [ -d $dir ]; then find $dir -type d -exec chmod 2770 {} +; find $dir -type f -exec chmod 0660 {} +; fi");
    }

    // Optional für webserver group:
    // run('chown -R :www-data {{release_path}}');
    // foreach ($sharedDirs as $dir) {
    //     run("if [ -d $dir ]; then chown -R :www-data $dir; fi");
    // }
});

// Hooks
after('deploy:prepare', 'fix:permissions');
after('deploy:symlink', 'fix:permissions');
after('deploy:success', 'fix:permissions');
after('deploy:symlink', 'typo3:cache:flush');
after('deploy:failed', 'deploy:unlock');
after('deploy:update_code', 'deploy:vendors');

// Rollback Task
desc('Rollback to previous release');
task('rollback', function () {
    run('cd {{deploy_path}} && ln -nfs $(ls -td releases/* | sed -n 2p) current');
    invoke('fix:permissions');
    invoke('typo3:cache:flush');
});