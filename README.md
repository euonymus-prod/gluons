# gluons
gluons is a database searvice to hold the all relations among everything in the universe.

# Service URL
http://gluons.link (English)

http://ja.gluons.link (日本語)

# Requirement

* Linux
* Apache
* MySQL
* PHP
* composer

# Prepare the application source

    $ cd /{source_dir_path}
    $ git checkout master
    $ composer install

※vendor/j7mbo/twitter-api-php が上手くgit管理できないため

# Prepare Databae

    Build MySQL database named '{dbname}'
    Build MySQL user named '{username}' (password is in config/app.php)

# Prepare Tables

    $ cd /{source_dir_path}
    $ bin/cake migrations migrate

# Prepare Table Datas

    $ mysql -u{username} -p{password} {dbname} < /{source_dir_path}


# Run local server

    $ cd /{source_dir_path}
    $ bin/cake server
