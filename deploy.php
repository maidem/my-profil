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

// WICHTIG: Shared-Struktur innerhalb von current/public
set('shared_base_path', '{{deploy_path}}/current/shared');

set('custom_shared_dirs', [
    'fileadmin',
    'uploads',
    'typo3temp',
]);
set('custom_shared_files', [
    '.htaccess',
    '.user.ini',
]);

// Außerhalb von public (aber innerhalb current)
set('custom_shared_dirs_outside_public', [
    'var',
    'config/sites',
]);
set('custom_shared_files_outside_public', [
    'config/system/additional.php',
]);

// Deployer-Standard shared deaktivieren
set('shared_dirs', []);
set('shared_files', []);

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
desc('Create shared structure inside current');
task('deploy:shared_custom', function () {
    $sharedBase = get('shared_base_path');
    
    // Erstelle shared-Basisverzeichnis
    run("mkdir -p $sharedBase/public");
    run("mkdir -p $sharedBase/var");
    run("mkdir -p $sharedBase/config/sites");
    run("mkdir -p $sharedBase/config/system");
    
    // Shared Dirs in public/
    foreach (get('custom_shared_dirs') as $dir) {
        $sharedDir = "$sharedBase/public/$dir";
        $releaseDir = "{{release_path}}/public/$dir";
        
        run("mkdir -p $sharedDir");
        run("rm -rf $releaseDir");
        run("ln -nfs $sharedDir $releaseDir");
    }
    
    // Shared Files in public/
    foreach (get('custom_shared_files') as $file) {
        $sharedFile = "$sharedBase/public/$file";
        $releaseFile = "{{release_path}}/public/$file";
        
        run("if [ ! -f $sharedFile ]; then touch $sharedFile; fi");
        run("rm -f $releaseFile");
        run("ln -nfs $sharedFile $releaseFile");
    }
    
    // Shared Dirs außerhalb von public/ (aber innerhalb current)
    foreach (get('custom_shared_dirs_outside_public') as $dir) {
        $sharedDir = "$sharedBase/$dir";
        $releaseDir = "{{release_path}}/$dir";
        
        run("mkdir -p $sharedDir");
        run("rm -rf $releaseDir");
        run("ln -nfs $sharedDir $releaseDir");
    }
    
    // Shared Files außerhalb von public/
    foreach (get('custom_shared_files_outside_public') as $file) {
        $sharedFile = "$sharedBase/$file";
        $releaseFile = "{{release_path}}/$file";
        $sharedDir = dirname($sharedFile);
        $releaseDir = dirname($releaseFile);
        
        run("mkdir -p $sharedDir");
        run("mkdir -p $releaseDir");
        run("if [ ! -f $sharedFile ]; then touch $sharedFile; fi");
        run("rm -f $releaseFile");
        run("ln -nfs $sharedFile $releaseFile");
    }
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
after('deploy:update_code', 'deploy:vendors');
after('deploy:vendors', 'deploy:shared_custom');
after('deploy:shared_custom', 'fix:permissions');
after('deploy:symlink', 'typo3:cache:flush');
after('deploy:failed', 'deploy:unlock');

// ACL Task deaktivieren (nicht supported auf Shared Hosting)
before('deploy:writable', 'fix:permissions');
task('deploy:writable', function () {
    writeln('Skipping default deploy:writable (Shared Hosting)');
});

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

// Überschreibe Standard deploy:setup um current-Verzeichnis zu erlauben
desc('Prepare host for deploy (custom for existing installations)');
task('deploy:setup', function () {
    $deployPath = get('deploy_path');
    
    // Erstelle grundlegende Verzeichnisse
    run("[ -d $deployPath ] || mkdir -p $deployPath");
    run("cd $deployPath");
    run("[ -d .dep ] || mkdir .dep");
    run("[ -d releases ] || mkdir releases");
    run("[ -d shared ] || mkdir shared");
    
    writeln(" Deploy setup completed (allows existing current directory)");
});