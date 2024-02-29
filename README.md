# Setup and stuff
This is a basic description of how to use and setup this migration script

## Code Overview
composer.json -> PHP setup uses composer to set up vendor files
Start.php -> turns on autoloader and calls the main controller 
Controllers/Main.php -> Starts with run() function, uncomment what steps you need to do, most of the work is done inside of this file
DB/Config.php -> simple file for pulling the configs
DB/CrabDatabaseConnection.php -> Sets up a connection to the CrabPlace DB, (MSSQL)?
DB/DatabaseConnection.php -> used for local DB for tracking migration status
Helpers/CustomerMigration.php -> used for querying data from the old DB and do some formatting of customer records
Helpers/MigrationStatus.php -> used for querying the local migration status DB
Helpers/OrderMigration.php -> used for querying data from the old DB and do some formatting of order records
Helpers/ShopifyClient.php -> Makes the actual API calls to Shopify
Helpers/ShopifyImport.php -> Wrapper for each type of API call to have the correct url
App.php
config-sample/( remove the -sample on the directory to have it be used by the system)
config-sample/app.php -> contains app configs for connecting to the databases
config-sample/shopify.php -> contains shopify configs 

## Composer
This project uses composer so you will need to run it for the initial setup.
composer install
composer dump-autoload

## Create table for migration
These MySQL tables were used for the migrations, and are filled in Main fillLocalDB() & fillLocalDBOrders()

CREATE TABLE `cp_customer_migration_status` (
  `cp_customer_id` int(11) NOT NULL,
  `shopify_customer_id` varchar(255) DEFAULT NULL,
  `migration_status` varchar(255) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `cp_order_migration_status` (
  `cp_order_id` int(11) NOT NULL,
  `shopify_order_id` varchar(255) DEFAULT NULL,
  `migration_status` varchar(255) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

## How it works
The migration script should be presetup as described above. After the CrabPlace ids are in the DB following running the fillLocalDB function, an attempt to migrate each customer one by one by running the migrationLoop function. If the account migration is successful, then the shopify_customer_id will be set with the customers shopify id and any issues will be identified in the migration_status column. If the migration fails, the shopify_customer_id will contain the error message from shopify to invnestigate later.

## Config loading
(depricated method) config.json was initially used along with src/DB/Config.php to load configs from a single json config
(current method) This was changed to loading the configs from php arrays based on the file name under config. eg: config/shopify.php for shopify configs

Config directory is not commited as it contains secret information like usernames and passwordss into the DB connations
You will want to start by changing the name of the config-sample directory and configuring all the requried params correctly
You should be able to connect to all DB and they should be setup corretly. 

## Running the functions
Using commandline run Start.php, this will trigger /src/Controllers/Main.php run() In here you should find two functions fillLocalDB & migrationLoop commented out. You will first want to run fillLocalDB by uncommenting it and then go back to the command line and run 'php Start.php'
You should check that migration table has been updated correctly containing cp customer ids.
You are now ready to go back to /src/Controllers/Main.php run() function comment out fillLocalDB line and uncomment migrationLoop and then go back to the command line and run 'php Start.php'
The migration will take a few days, if the connection is lost and/or the application fails, restart it by running php Start.php and it will pickup were it left off as best as possible.
