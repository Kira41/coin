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
generated in JavaScript with `Date.now()`. User accounts use the same approach:
the `personal_data.user_id` column is also a `BIGINT` so IDs created with
`Date.now()` are inserted without overflowing.

The `personal_data` table now includes the user's own bank details used when
submitting withdrawal requests: `userBankName`, `userAccountName`,
`userAccountNumber`, `userIban` and `userSwiftCode`. Separate deposit
information is kept in the `bank_withdrawl_info` table. This table stores the
bank coordinates shown on the deposit screen and each user has at most one
record. These deposit details are filled in by an administrator when creating or
editing a user. The admin dashboard's create and edit user modals now include
input fields for these coordinates so they can be entered or updated alongside
other personal data.

An additional table `admins_agents` stores admin and agent accounts. Each row
contains an email, hashed password and an `is_admin` flag, plus a `created_by`
field referencing the admin who created the record. The `personal_data` table
now includes a `linked_to_id` column storing the `admins_agents.id` of the
creator. When inserting or updating these records via `admin_setter.php`, make
sure the password you send is pre-hashed on the client using the provided MD5
algorithm.
Each `email` in `admins_agents` must be unique, enforced by a `UNIQUE(email)`
constraint in the schema.

Foreign keys from tables such as `transactions`, `deposits`, `retraits` and
`tradingHistory` now include `ON DELETE CASCADE`. Removing a row from
`personal_data` will automatically clean up any related records, preventing
foreign key errors.

## Wallet management

Each wallet row includes edit and delete icons. Clicking the **edit** icon opens
a modal where you can update the address or its label. The **trash** icon
removes the wallet entirely. Edits and deletions are sent to
`get_wallets.php`, and the wallet list refreshes immediately to show the latest
data.

## Admin dashboard

`insertdata.sql` seeds a default administrator account (`admin@scampia.io`) with
ID `1`. To load data for this account you now must be authenticated. The
`admin_getter.php` endpoint looks for a session variable named `admin_id` or an
`Authorization: Bearer <id>` header identifying the admin. If neither is
present, the request is rejected with `401 Unauthorized`. Once authenticated,
`dashboard_admin.html` will display the admin's agents and associated users.
Use the "Créer Agent" form to add new agents under the logged‑in admin.

Deleting an agent with `admin_setter.php` now removes all of the users tied to
that account. Each affected user's rows in `personal_data`, `wallets`,
`transactions`, `tradingHistory`, `notifications`, `loginHistory`, `deposits`
and `bank_withdrawl_info` are deleted before the agent record itself is
removed. The same cleanup occurs when deleting an individual user.

Use `admin_login.php` to sign in. POST `email` and `password`; a successful login starts a session and stores `admin_id` for subsequent requests.

## Admin Login

`dashboard_admin.html` now embeds its own login form. You can also sign in by POSTing `email` and `password` to `admin_login.php`. If the credentials are valid the endpoint creates a session and sets a cookie storing `admin_id`. Keep this cookie for all subsequent calls to `admin_getter.php` and other admin actions so the server knows who you are. Tools like `curl -c cookies.txt -b cookies.txt` can handle the cookie automatically.


## User Login

`dashbord_user.html` now includes a login form. Submit your email and password to `user_login.php`; on success the script stores your `user_id` in `localStorage` and loads the dashboard for that account. Each successful login is also recorded in the `loginHistory` table along with the IP address and device used.

## Automated trade closing

Open trades can be finalized automatically even when users are offline. The `cron_trading.php` script checks all orders with the status `En cours`, fetches the latest price from Binance and updates the order with the resulting profit or loss. To keep trading positions active, schedule the script with cron for example:

```cron
* * * * * php /path/to/cron_trading.php
```

This will close any open trades once per minute using the current market price.

### Order types and stop loss

User trades can be created with several execution methods:

- **Ordre au marché** – buy or sell immediately at the best price.
- **Ordre à cours limité** – specify the exact price to execute.
- **Ordre stop** – becomes a market order when the stop price is reached.
- **Stop‑limit** – after the stop price is hit a limit order is placed.

For risk management the following stop loss modes are available:

- Fixed price stop
- Percentage based stop
- Time based exit
- Trailing stop which follows the market in your favor.

Trades may also combine a take profit and stop loss using an OCO (One Cancels the Other) order. When one of the two triggers the other is automatically cancelled.

All parameters are stored in the `details` column of the `tradingHistory` table so they remain active even when the user is offline. The `cron_trading.php` script evaluates these rules on each run and finalizes trades whose conditions are met.