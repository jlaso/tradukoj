Tradukoj, tradukoj por programistoj (translations for developers)
===================================

In esperanto TRADUKOJ means translations (and is pronounced with the stress in the U)

Please: follow @tradukoj in twitter to be updated!.

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

move to vagrant folder (```cd vagrant```) and start vagrant machine with ```vagrant up``, please get some coffee for the first time

2) Checking your System Configuration
-------------------------------------

Before starting coding, make sure that your local system is properly
configured for Symfony.

Execute the `check.php` script from the command line:

    php app/check.php

If you get any warnings or recommendations, fix them before moving on.


3) Getting started with Tradukoj
-------------------------------

If the vagrant machine has been started successfully, you can start tradukoj with this url:

http://10.10.10.8


4) Collaboration
----------------

Please, feel free to contribute, or proposal improvements.

Thank you so much to spend your time testing this project.


5) Bundles
----------

Currently there are two bundles to communicate symfony2 projects or not with tradukoj, in order to centralize translations.

- [translations-apibundle](https://github.com/jlaso/translations-apibundle)
- [tradukoj-po-mo-module](https://github.com/jlaso/tradukoj-po-mo-module)

The connection between this modules and the server occurs with socket native implementation. The explanation of this solution has been explained for me in several occasions:

- [Talk in spanish in GeeksHubs, Valencia 2014-05-15](http://youtu.be/zjZG3eY_QNg)
- [Talk in spanish in DrupalCamp Valencia 2014](https://vimeo.com/channels/drupalcampspain2014/98160710)

Explanations in english are welcome.



References:

  * [www.tradukoj.com][1] - Official site
   
Enjoy!

[1]:  https://www.tradukoj.com
