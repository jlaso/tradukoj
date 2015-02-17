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
        'nano',
        'htop',
        'php5-cli',
        'php5-dev',
        'git',
        "php-pear",
        'cifs-utils',
        'curl',
    ]:
    ensure => 'latest'
}

class { ['php', 'php::extension::mysql', 'php::extension::mongo', 'php::extension::intl', 'php::extension::curl', 'php::composer', 'php::composer::auto_update']:
    before => Exec['composer_config']
}

php::config { 'opcache.enable_cli=1':
    file    => '/etc/php5/cli/conf.d/05-opcache.ini',
    require   => Package['php5-cli']
}

file {'/etc/php5/conf.d/lzf.conf':
  ensure => present,
  owner => root, group => root, mode => 444,
  content => "extension=lzf.so\n",
}

exec {'composer_config':
    command => '/usr/local/bin/composer config -g github-oauth.github.com 462578c8baf9d72e181c82ee279887d785614881',
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

#exec { 'tradukoj_install':
#    command     => '/home/vagrant/tradukoj/app/install.sh',
#    user        => 'vagrant',
#    cwd         => '/home/vagrant/tradukoj',
#    logoutput   => true,
#    timeout     => 1800,
#    require     => Exec['composer_config']
#}
