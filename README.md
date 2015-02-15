Tradukoj, tradukoj por programistoj (translations for developers)
===================================

In esperanto TRADUKOJ means translations (and is pronounced with the stress in the U)

1) Installing
-------------

When it comes to installing you have the following options.

### Use Composer create-project (*recommended*)

As Symfony uses [Composer][2] to manage its dependencies, the recommended way
to create a new project is to use it.

If you don't have Composer yet, download it following the instructions on
http://getcomposer.org/ or just run the following command:

    curl -s http://getcomposer.org/installer | php

Then, use the `create-project` command to generate a new Symfony application:

    php composer.phar create-project jlaso/tradukoj path/to/install
    
    composer create-project --repository-url=http://tradukoj.dev  jlaso/tradukoj tradukoj

Composer will install Tradukoj and all its dependencies under the
`path/to/install` directory.

### Use Composer and start virtual server

Please, note that vagrant and bindfs need to bee installed into the system

    vagrant plugin install vagrant-bindfs
    
if you get an error for vboxsf upping vagrant check this [link](http://stackoverflow.com/questions/22717428/vagrant-error-failed-to-mount-folders-in-linux-guest) 

2) Checking your System Configuration
-------------------------------------

Before starting coding, make sure that your local system is properly
configured for Symfony.

Execute the `check.php` script from the command line:

    php app/check.php

Access the `config.php` script from a browser:

    http://localhost/path/to/tradukoj/app/web/config.php

If you get any warnings or recommendations, fix them before moving on.


3) Getting started with Tradukoj
-------------------------------


Lets see the config paramteres we need to adjust


References:

  * [www.tradukoj.com][1] - Official site
   
Enjoy!

[1]:  https://www.tradukoj.com
