# Migration Guide

The schema previously stored monetary values as text. This update converts those fields to numeric types.

## Database Changes
Run the following SQL statements on your production database:

```
ALTER TABLE personal_data
  MODIFY balance DECIMAL(18,2),
  MODIFY totalDepots DECIMAL(18,2),
  MODIFY totalRetraits DECIMAL(18,2),
  MODIFY nbTransactions INT;

ALTER TABLE transactions MODIFY amount DECIMAL(18,2);
ALTER TABLE deposits MODIFY amount DECIMAL(18,2);
ALTER TABLE retraits MODIFY amount DECIMAL(18,2);
ALTER TABLE trading_history
  MODIFY montant DECIMAL(18,2),
  MODIFY prix DECIMAL(18,2),
  MODIFY profitPerte DECIMAL(18,2);
```

## Cleaning Existing Data
If your tables contain values with currency symbols (e.g. `$100` or `1,000 $`),
you need to strip those characters before running the `ALTER TABLE` commands.
The following examples show how to sanitize a column:

```
UPDATE transactions SET amount = REPLACE(REPLACE(amount, '$', ''), ',', '') WHERE amount LIKE '%$%';
UPDATE personal_data SET balance = REPLACE(REPLACE(balance, '$', ''), ',', '');
```
Adjust these queries for each column that contains formatted amounts.

After cleaning the data and applying the schema changes, the updated PHP code
will correctly bind numeric values when inserting rows.
