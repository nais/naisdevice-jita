# Database schema migrations

This directory contains database migration scripts that is used to automatically migrate the database schema of the app to the latest version. The migration occurs when the container starts.

The migration script will scan this directory, and execute the files in alphabetical order, so it is critical that the naming of the files are correct.

## Add migrations

Each migration file must use the following naming convention:

    `XXXX-some-name.sql`

Where XXXX reflects a version of the schema, for instance `0001` or `0002`. Each file should include a separate transaction where the migration occurs, and the last thing each transaction should do is to bump the `version` column in the `migrations` table so that the migration will only occur once as long as the `migrations` table stays intact.

An example of a migrations file:

```sql
START TRANSACTION ISOLATION LEVEL SERIALIZABLE READ WRITE;

-- Update tables, cols or convert existing content
ALTER TABLE ...

-- Update version
INSERT INTO migrations (version, created)
VALUES (<version>, NOW());

COMMIT;
```

You can also have a look at existing migrations in this directory for inspiration.
