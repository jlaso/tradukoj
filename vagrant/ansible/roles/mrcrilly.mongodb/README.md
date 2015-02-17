Role Name
=========

MongoDB Server management role. Installs and manages the configuration for MongoDB 2.4.

**Does NOT support MongoDB 2.6 - yet**

Requirements
------------

None.

Role Variables
--------------

All MongoDB Server configuration (2.4) has been placed into a dict, mrcrilly_mongodb_configuration.key, where 'key' is the name of the configuration item in mongodb.conf.

Dependencies
------------

None.

Example Playbook
----------------

Easy to use:

    - hosts: servers
      roles:
         - mrcrilly.mongodb

License
-------

BSD

Author Information
------------------

- Michael Crilly
- http://mrcrilly.me/
- @mrcrilly
