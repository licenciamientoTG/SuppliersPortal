SET NOCOUNT ON;

CREATE TABLE #hits (
    database_name sysname NOT NULL
);

DECLARE @db sysname;
DECLARE @sql nvarchar(max);

DECLARE db_cursor CURSOR LOCAL FAST_FORWARD FOR
SELECT name
FROM sys.databases
WHERE name LIKE '1G[_]%'
ORDER BY name;

OPEN db_cursor;
FETCH NEXT FROM db_cursor INTO @db;

WHILE @@FETCH_STATUS = 0
BEGIN
    SET @sql = N'
        IF EXISTS (
            SELECT 1
            FROM ' + QUOTENAME(@db) + N'.sys.views v
            INNER JOIN ' + QUOTENAME(@db) + N'.sys.schemas s ON s.schema_id = v.schema_id
            WHERE s.name = N''dbo''
              AND v.name = N''vtt_cat_prov''
        )
        BEGIN
            INSERT INTO #hits (database_name) VALUES (N''' + REPLACE(@db, '''', '''''') + N''');
        END
    ';

    EXEC sys.sp_executesql @sql;

    FETCH NEXT FROM db_cursor INTO @db;
END;

CLOSE db_cursor;
DEALLOCATE db_cursor;

SELECT database_name
FROM #hits
ORDER BY database_name;
