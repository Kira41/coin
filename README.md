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

## Wallet management

Each wallet row includes edit and delete icons. Clicking the **edit** icon opens
a modal where you can update the address or its label. The **trash** icon
removes the wallet entirely. Edits and deletions are sent to
`get_wallets.php`, and the wallet list refreshes immediately to show the latest
data.

