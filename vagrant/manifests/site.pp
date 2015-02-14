# -*- mode: ruby -*-
# vi: set ft=ruby :

group { 'puppet': ensure => present }

file { "/home/vagrant/tradukoj":
    ensure => "directory",
    owner  => "vagrant",
    group  => "vagrant",
    mode   => 775,
}

class {'apt':
    always_apt_update => true
}

package {
    [
        'imagemagick',
        'vim',
        'htop',
        'php5-cli',
        'git',
        'cifs-utils',
        'curl',
        'redis-server'
    ]:
    ensure => 'latest'
}

class { ['php', 'php::extension::mysql', 'php::extension::intl', 'php::extension::redis', 'php::extension::curl', 'php::composer', 'php::composer::auto_update']:
    before => Exec['composer_config']
}

php::config { 'opcache.enable_cli=1':
    file    => '/etc/php5/cli/conf.d/05-opcache.ini',
    require   => Package['php5-cli']
}

exec {'composer_config':
    command => '/usr/local/bin/composer config -g github-oauth.github.com f0a71c9745759dd6ca7b4dc45355d5d407dc9667',
    environment => 'HOME=/home/vagrant',
    cwd => '/home/vagrant',
    user => 'vagrant',
    logoutput => true
}

class { 'apache':
    default_mods => false,
    mpm_module   => 'prefork',
    user         => 'vagrant',
    group        => 'vagrant'
}

include apache::mod::rewrite
include apache::mod::php

apache::vhost { 'tradukoj.dev':
    port          => '80',
    docroot       => '/home/vagrant/tradukoj/web',
    docroot_owner => 'vagrant',
    docroot_group => 'vagrant',
    directories   => [
        {
            path           => '/home/vagrant/tradukoj/web',
            options        => ['Indexes','FollowSymLinks','MultiViews'],
            allow_override => ['all'],
            allow => 'from All'
        },
    ],
    serveradmin => 'admin@tradukoj.com',
}

class { 'mysql::server':
    root_password => 'root',
    before => Exec['tradukoj_install']
}

class { 'mysql::client':
    before => Exec['tradukoj_install']
}

exec { 'tradukoj_install':
    command     => '/home/vagrant/tradukoj/app/install.sh',
    user        => 'vagrant',
    cwd         => '/home/vagrant/tradukoj',
    logoutput   => true,
    timeout     => 1800,
    require     => Exec['composer_config']
}
