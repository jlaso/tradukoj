class php::pecl {
    include php
       exec {
         'printf "\n\n" | pecl install php-lzf':
         require => Package["php-pear"]
       }
}

#class php::packages::lzf {
#    php::pecl{'lzf':
#        mode => 'cli',
#    }
#}