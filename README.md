# Coin Dashboard

This project uses small PHP helpers (`getter.php` and `setter.php`) to read and update user data stored in a MySQL database. The schema is defined in `createtable.sql` and example data is provided in `insertdata.sql`.

## Database setup

1. Make sure the PHP MySQL PDO extension is installed and a MySQL server is running.
2. Create a database named `coin_db` and load the schema and sample data:
   ```sh
   mysql -u root coin_db < createtable.sql
   mysql -u root coin_db < insertdata.sql
   ```
3. The PHP scripts connect to `coin_db` on `localhost` using the `root` user with an empty password. Update the connection settings in `getter.php` and `setter.php` if your environment differs.

The dashboard pages (`dashbord_user.html` and `script.js`) request data from `getter.php` and send updates to `setter.php`.
