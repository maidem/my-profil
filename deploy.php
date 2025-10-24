<?php
namespace Deployer;

require 'recipe/common.php';

set('application', 'my-profil');
set('repository', 'git@github.com:maidem/my-profil.git');

set('branch', getenv('DEPLOY_BRANCH') ?: 'main');
set('bin/php', '/usr/bin/php8.3');
set('ssh_private_key', getenv('DEPLOY_SSH_KEY'));

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

set('writable_dirs', [
    'var',
    'public/fileadmin',
    'public/uploads',
    'public/typo3temp',
]);
set('allow_anonymous_stats', false);
set('keep_releases', 5);

host('live')
    ->set('hostname', getenv('DEPLOY_HOST'))
    ->set('remote_user', getenv('DEPLOY_SSH_USER'))
    ->set('deploy_path', getenv('DEPLOY_PATH'))
    ->set('ssh_options', [
        'ControlMaster' => 'no',
        'ControlPersist' => 'no',
        'ForwardAgent' => 'yes',
        'StrictHostKeyChecking' => 'no',
    ]);

after('deploy:failed', 'deploy:unlock');

// Custom Tasks
desc('Initiales Setup des Zielservers');
task('deploy:setup', function () {
    run('[ -d {{deploy_path}} ] || mkdir -p {{deploy_path}}');
    run('mkdir -p {{deploy_path}}/.dep {{deploy_path}}/releases {{deploy_path}}/shared');
    run('chmod 755 {{deploy_path}}/.dep');
    run('rm -rf {{deploy_path}}/releases/* {{deploy_path}}/.dep/releases_log {{deploy_path}}/.dep/latest_release');
    run('rm -rf {{deploy_path}}/current');
});

task('deploy:lock', function () {
    if (test('[ -f {{deploy_path}}/.dep/deploy.lock ]')) {
        throw new \Exception('Deploy ist gesperrt!');
    }
    run('touch {{deploy_path}}/.dep/deploy.lock');
});

task('deploy:unlock', function () {
    run('rm -f {{deploy_path}}/.dep/deploy.lock');
});

task('deploy:writable', function () {
    run('mkdir -p {{release_path}}/var {{release_path}}/public/fileadmin {{release_path}}/public/uploads {{release_path}}/public/typo3temp');
    run('chmod -R 755 {{release_path}}/var {{release_path}}/public/fileadmin {{release_path}}/public/uploads {{release_path}}/public/typo3temp');
});

task('deploy:symlink', function () {
    run('rm -rf {{deploy_path}}/current');
    run('ln -nfs {{deploy_path}}/releases/{{release_name}} {{deploy_path}}/current');
});