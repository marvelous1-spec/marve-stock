# MySQL database

Database name: `ngx_invest`.

This is the production-oriented foundation for the application. It deliberately has no demo money, sample prices, fake orders, or fabricated holdings.

## Import

Import `schema.sql` to a new MySQL 8+ database:

```powershell
C:\xampp\mysql\bin\mysql.exe -u root -e "SOURCE C:/Users/HomePC/Documents/project1/database/mysql/schema.sql"
```

`seed_dev.sql` intentionally contains no financial data.

## Notes

- Money and prices use `DECIMAL`, not floating point.
- The ledger is append-only at the application level; corrections require reversal or adjustment records.
- Apply future production changes as reviewed, additive migrations rather than re-importing the schema.
