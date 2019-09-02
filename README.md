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


# MEMO: mails table

At 2019-08-26. I created mails table briefly. But never set any configuration on Migration and Vagrant Setup.
So you have to manually setup your mails table on your own.
mails migration shcema definition is below.

```php
    public function up()
    {

        $this->table('mails')
            ->addColumn('id', 'integer', [
                'autoIncrement' => true,
                'default' => null,
                'limit' => 11,
                'null' => false,
                'signed' => false,
            ])
            ->addPrimaryKey(['id'])
            ->addColumn('name', 'string', [
                'default' => '',
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('organization', 'string', [
                'default' => '',
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('department', 'string', [
                'default' => '',
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('email', 'string', [
                'default' => '',
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('topic', 'string', [
                'default' => '',
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('body', 'text', [
                'default' => null,
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('created', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('modified', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->create();
    }

    public function down()
    {
        $this->dropTable('mails');
    }
```
