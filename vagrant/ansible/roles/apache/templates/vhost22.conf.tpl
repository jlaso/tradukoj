# Default Apache virtualhost template

<VirtualHost *:80>
    ServerAdmin webmaster@localhost
    DocumentRoot {{ doc_root }}
{% set servernames = servername.split() %}
{% for servername in servernames %}
{% if loop.first %}
    ServerName {{ servername }}
{% else %}
    ServerAlias {{ servername }}
{% endif %}
{% endfor %}

    <Directory {{doc_root}}>
        AllowOverride All
        Order allow,deny
        Allow from all
        RewriteEngine On
        DirectoryIndex app_dev.php
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteRule ^(.*)$ app_dev.php [QSA,L]
        RewriteBase /
    </Directory>
</VirtualHost>
