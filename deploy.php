<?php
namespace Deployer;

require 'recipe/common.php';

// --- Projekt ---
set('application', 'my-profil');
set('repository', 'git@github.com:maidem/my-profil.git');

// Branch aus Secret, sonst main
set('branch', function () {
    return getenv('DEPLOY_BRANCH') ?: 'main';
});

// PHP-Binary auf dem Server
set('bin/php', '/usr/bin/php');

// SSH Key aus GitHub Secret
set('ssh_private_key', getenv('DEPLOY_SSH_KEY'));

// Standard TYPO3 shared Konfiguration
set('shared_dirs', [
    'var',
    'public/fileadmin',
    'public/uploads',
    'public/typo3temp'
]);

set('shared_files', [
    'config/system/additional.php',
    '.env'
]);

// Deployer-Standard writable dirs
set('writable_dirs', [
    'var',
    'public/fileadmin',
    'public/uploads', 
    'public/typo3temp',
]);
set('allow_anonymous_stats', false);
set('keep_releases', 5);

// --- Host Definition ---
host('live')
    ->set('hostname', getenv('DEPLOY_HOST') ?: 'example.com')
    ->set('remote_user', getenv('DEPLOY_USER') ?: 'deployer')
    ->set('deploy_path', getenv('DEPLOY_PATH') ?: '/var/www/maidem.de/')
    ->set('ssh_options', [
        'ControlMaster' => 'no',
        'StrictHostKeyChecking' => 'no',
    ]);

set('http_user', 'hosting227931');
set('ssh_multiplexing', false);

// --------------------------------------
// Custom Shared Task (innerhalb von current)
// --------------------------------------
// --------------------------------------
// Hooks
// --------------------------------------
after('deploy:failed', 'deploy:unlock');

// Nur die absolut notwendigen Overrides
desc('Prepare host for deploy');
task('deploy:setup', function () {
    run('[ -d {{deploy_path}} ] || mkdir -p {{deploy_path}}');
    cd('{{deploy_path}}');
    run('[ -d .dep ] || mkdir -p .dep');
    run('[ -d releases ] || mkdir -p releases');  
    run('[ -d shared ] || mkdir -p shared');
    run('chmod 755 .dep');
    
    // Räume alte/fehlerhafte Releases auf
    run('rm -rf releases/*');
    run('rm -f .dep/releases_log .dep/latest_release');
    
    // Entferne altes current Verzeichnis/Symlink für sauberen Start
    run('rm -rf current');
});

// Einfache Lock-Mechanismus ohne komplizierte Dateinamen
task('deploy:lock', function () {
    cd('{{deploy_path}}');
    if (test('[ -f .dep/deploy.lock ]')) {
        throw new \Exception('Deploy is locked!');
    }
    run('touch .dep/deploy.lock');
});

task('deploy:unlock', function () {
    cd('{{deploy_path}}');
    run('rm -f .dep/deploy.lock');
});

// Fix für Symlink-Problem: Räume current vor Symlink auf
task('deploy:symlink', function () {
    cd('{{deploy_path}}');
    
    // Entferne bestehende current (egal ob Verzeichnis oder Symlink)
    run('rm -rf current');
    
    // Erstelle Symlink zum aktuellen Release (relativer Pfad zum releases/ Verzeichnis)
    run('ln -nfs releases/{{release_name}} current');
});

// --------------------------------------
// TYPO3 Tasks
// --------------------------------------
desc('Flush TYPO3 cache');
task('typo3:cache:flush', function () {
    run('{{bin/php}} {{current_path}}/vendor/bin/typo3 cache:flush || true');
});

// --------------------------------------
// Permissions Task (Shared Hosting kompatibel)
// --------------------------------------
desc('Set correct permissions');
task('fix:permissions', function () {
    $sharedBase = get('shared_base_path');
    
    // Permissions für shared-Verzeichnisse
    $dirs = array_merge(
        array_map(fn($d) => "$sharedBase/public/$d", get('custom_shared_dirs')),
        array_map(fn($d) => "$sharedBase/$d", get('custom_shared_dirs_outside_public'))
    );

    foreach ($dirs as $dir) {
        run("mkdir -p $dir || true");
        run("find $dir -type d -exec chmod 775 {} \\; 2>/dev/null || true");
        run("find $dir -type f -exec chmod 664 {} \\; 2>/dev/null || true");
    }
});

// --------------------------------------
// Hooks
// --------------------------------------
after('deploy:failed', 'deploy:unlock');

// --------------------------------------
// Rollback Task!
// --------------------------------------
desc('Rollback to previous release');
task('rollback', function () {
    run('cd {{deploy_path}} && ln -nfs $(ls -td releases/* | sed -n 2p) current');
    invoke('fix:permissions');
    invoke('typo3:cache:flush');
});

// --------------------------------------
// Hooks
// --------------------------------------

// Nur die absolut notwendigen Overrides
desc('Prepare host for deploy');
task('deploy:setup', function () {
    run('[ -d {{deploy_path}} ] || mkdir -p {{deploy_path}}');
    cd('{{deploy_path}}');
    run('[ -d .dep ] || mkdir -p .dep');
    run('[ -d releases ] || mkdir -p releases');  
    run('[ -d shared ] || mkdir -p shared');
    run('chmod 755 .dep');
    
    // Räume alte/fehlerhafte Releases auf
    run('rm -rf releases/*');
    run('rm -f .dep/releases_log .dep/latest_release');
    
    // Entferne altes current Verzeichnis/Symlink für sauberen Start
    run('rm -rf current');
});

// Einfache Lock-Mechanismus ohne komplizierte Dateinamen
task('deploy:lock', function () {
    cd('{{deploy_path}}');
    if (test('[ -f .dep/deploy.lock ]')) {
        throw new \Exception('Deploy is locked!');
    }
    run('touch .dep/deploy.lock');
});

task('deploy:unlock', function () {
    cd('{{deploy_path}}');
    run('rm -f .dep/deploy.lock');
});

// Fix für Symlink-Problem: Räume current vor Symlink auf
task('deploy:symlink', function () {
    cd('{{deploy_path}}');
    
    // Entferne bestehende current (egal ob Verzeichnis oder Symlink)
    run('rm -rf current');
    
    // Erstelle Symlink zum aktuellen Release (relativer Pfad zum releases/ Verzeichnis)
    run('ln -nfs releases/{{release_name}} current');
});