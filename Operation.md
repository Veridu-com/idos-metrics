Operation manual
=================

# Configuration

You need to set some environment variables in order to configure the Metrics daemon, such as in the following example:

* `IDOS_SQL_DRIVER`: indicates the SQL database driver to use (default: 'psql');
* `IDOS_SQL_HOST`: the SQL database server host name (default: 'localhost');
* `IDOS_SQL_PORT`: the SQL database server port (default: 5432);
* `IDOS_SQL_NAME`: the SQL database name (default: 'idos-api');
* `IDOS_SQL_USER`: the username used to authenticate within the SQL server (default: 'idos-api');
* `IDOS_SQL_PASS`: the password used to authenticate within the SQL server (default: 'idos-api');
* `IDOS_SALT_USER`: the salt string to use in the user metrics table (default: '').

You may also set these variables using a `.env` file in the project root.

# Running

In order to start the Metrics daemon you should run in the terminal:

```
./metrics-cli.php metrics:daemon [-d] [-l path/to/log/file] functionName serverList
```

* `functionName`: gearman function name
* `serverList`: a list of the gearman servers
* `-d`: enable debug mode
* `-l`: the path for the log file

Example:

```
./metrics-cli.php metrics:daemon -d -l log/metrics.log metrics localhost
```
