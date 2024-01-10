# lum.db-pdo.php

## Summary

Extension to [lum-db] for working with PDO/SQL databases.

## Namespace

`Lum\DB\PDO`

## Classes and traits

| Class                   | Description                                       |
| ----------------------- | ------------------------------------------------- |
| `InsertArray`           | A simplistic INSERT statement builder.            |
| `Item`                  | A class representing an Item in an ORM model.     |
| `Model`                 | An abstract class representing an ORM model.      |
| `Query`                 | An SQL Query builder class.                       |
| `Reference`             | A small class representing a DB reference.        |
| `ResultArray`           | A result class that acts like an array.           |
| `ResultBag`             | A quirky extension of ResultArray.                |
| `ResultSet`             | A lazy loading result class. A good default.      |
| `Simple`                | A simple PDO wrapper library.                     |
| `WhereArray`            | A simplistic WHERE statement builder.             |
| `Schemata\Tables`       | A class representing a full set of table schemas. |
| `Schemata\*`            | A bunch of internal classes.                      |
| `Simple\Alter`          | A trait with  ALTER TABLE methods.                |
| `Simple\NativeDB`       | A trait for running engine-specific SQL files.    |

The `Lum\DB\PDO\Schemata\*` classes currently use their own custom JSON-based
format for the schema definition files. They are limited to working with
`Lum\DB\PDO\Simple` classes with the `Lum\DB\PDO\Simple\NativeDB` trait.
They will likely be replaced by something better in the future.

## Official URLs

This library can be found in two places:

 * [Github](https://github.com/supernovus/lum.db-pdo.php)
 * [Packageist](https://packagist.org/packages/lum/lum-db-pdo)

## Authors

- Timothy Totten

## License

[MIT](https://spdx.org/licenses/MIT.html)

[lum-db]: https://github.com/supernovus/lum.db.php

