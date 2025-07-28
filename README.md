# Coin Dashboard

This project uses small PHP helpers (`getter.php` and `setter.php`) to read and update user data stored in a MySQL database. The schema is defined in `sql/createtable.sql` and example data is provided in `sql/insertdata.sql`.

## Database setup

1. Make sure the PHP MySQL PDO extension is installed and a MySQL server is running.
2. Create a database named `coin_db` and load the schema and sample data:
   ```sh
   mysql -u root coin_db < sql/createtable.sql
   mysql -u root coin_db < sql/insertdata.sql
   ```
3. The PHP scripts connect to `coin_db` on `localhost` using the `root` user with an empty password. Update the connection settings in `getter.php` and `setter.php` if your environment differs.

The dashboard pages (`dashbord_user.html` and `js/updatePrices.js`) request data from `php/getter.php` and send updates to `php/setter.php`.
`js/updatePrices.js` now fetches wallet addresses from `php/get_wallets.php`, which returns `SELECT * FROM wallets WHERE user_id = ?` in JSON. The `wallets` table stores
crypto addresses with a `BIGINT` `id` so each entry keeps the unique identifier
generated in JavaScript with `Date.now()`. User accounts use the same approach:
the `personal_data.user_id` column is also a `BIGINT` so IDs created with
`Date.now()` are inserted without overflowing.

All tables now use the **InnoDB** storage engine and any `AUTO_INCREMENT`
columns have been widened to `BIGINT` to prevent errors when new rows are
created.

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

Wallets now store the amount of each currency owned by a user. The `wallets`
table contains an `amount` column and one row per `(user, currency)` pair. If a
user buys a currency for the first time a new row is created with the address
set to `local address`.

Each wallet row includes edit and delete icons. Clicking the **edit** icon opens
a modal where you can update the address or its label. The **trash** icon
removes the wallet entirely. Edits and deletions are sent to
`get_wallets.php`, and the wallet list refreshes immediately to show the latest
data. The wallet table on the user dashboard now also displays the current
balance for each address. A separate cron task `cron_wallet_usd.php` updates the
`usd_value` column of every wallet by fetching live prices from Binance. This
value is shown in the wallet table so users can see the approximate amount in
USD for each of their crypto holdings.

The trading history table was updated as well. Amounts are shown with the
traded coin symbol instead of dollars, e.g. `100 XRP`.

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

To keep wallet values in sync with the market, schedule `cron_wallet_usd.php` as well:

```cron
* * * * * php /path/to/cron_wallet_usd.php
```

### Order type

Only market orders are supported. Trades are executed immediately at the current market price and recorded in the `trades` table.

When querying Binance for live prices remember that pairs use the `USDT` quote currency. A pair like `ADA/USD` should be converted to `ADAUSDT` before requesting the price.

Example pseudo-code for order execution:

```php
// Market order execution
$price = getLivePrice($pair);
$total = $price * $quantity;
if ($side === 'buy') {
    // deduct dollars from the user's account balance
    deductFromAccount($userId, $total);
    addOrUpdateWallet($userId, $base, $quantity, 'local address');
} else {
    deductFromWallet($userId, $base, $quantity);
    // credit dollars back to the account
    addToAccount($userId, $total);
}
recordTrade($userId, $pair, $side, $quantity, $price);
```
