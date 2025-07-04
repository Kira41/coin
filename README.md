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
`script.js` now fetches wallet addresses from `get_wallets.php`, which returns `SELECT * FROM wallets WHERE user_id = ?` in JSON. The `wallets` table stores
crypto addresses with a `BIGINT` `id` so each entry keeps the unique identifier
generated in JavaScript with `Date.now()`.

The `personal_data` table now includes columns for storing default bank details:
`userBankName`, `userAccountName`, `userAccountNumber`, `userIban` and
`userSwiftCode`. A helper table `bank_withdrawl_info` stores the default bank
information shown on the deposit screen. Each record is tied to a specific user
via a `user_id` column so multiple users can manage their own withdrawal
details.

An additional table `admins_agents` stores admin and agent accounts. Each row
contains an email, hashed password and an `is_admin` flag, plus a `created_by`
field referencing the admin who created the record. The `personal_data` table
now includes a `linked_to_id` column storing the `admins_agents.id` of the
creator. When inserting or updating these records via `admin_setter.php`, make
sure the password you send is already hashed using PHP's `password_hash()`.
Each `email` in `admins_agents` must be unique, enforced by a `UNIQUE(email)`
constraint in the schema.

## Wallet management

Each wallet row includes edit and delete icons. Clicking the **edit** icon opens
a modal where you can update the address or its label. The **trash** icon
removes the wallet entirely. Edits and deletions are sent to
`get_wallets.php`, and the wallet list refreshes immediately to show the latest
data.

## Admin dashboard

`insertdata.sql` seeds a default administrator account (`admin@example.com`) with
ID `1`. Opening `dashboard_admin.html` will automatically display this admin's
data. `admin_getter.php` now defaults to ID `1` when no `admin_id` parameter is
supplied, so you can browse the admin interface without logging in. Use the
"Créer Agent" form to add new agents under the default admin.

