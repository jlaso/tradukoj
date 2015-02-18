#!/bin/bash

cd /vagrant

# Copies the parameters dist file to the actual parameters.yml.
# If you run composer manually, you will benefit from Incenteev
# ParameterHandler, which will interactively ask you for
# the parameters to be copied.
if [ ! -e app/config/parameters.yml ]; then
    cp app/config/parameters.yml.dis app/config/parameters.yml
fi

# Firing up composer. Better to invoke the INSTALL than an UPDATE
HOME=$(pwd) sh -c 'composer install --no-interaction'

# Creating database schema and tables
/usr/bin/env php app/console --no-interaction doctrine:database:drop --force
/usr/bin/env php app/console --no-interaction doctrine:database:create
/usr/bin/env php app/console --no-interaction doctrine:schema:create

# creating user in mongo and create schema
echo 'db.addUser("tradukoj","tradukoj");' | mongo tradukoj
/usr/bin/env php app/console --no-interaction doctrine:mongodb:schema:create

# Allowed fixtures go here
/usr/bin/env php app/console --no-interaction tradukoj:init:data

# Load first project
/usr/bin/env php app/console jlaso:translations:dump
/usr/bin/env php app/console jlaso:translations:sync

# Assets & Assetic
/usr/bin/env php app/console --no-interaction assets:install web --symlink
/usr/bin/env php app/console --no-interaction assetic:dump
