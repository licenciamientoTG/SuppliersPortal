SET NOCOUNT ON;

CREATE TABLE #terms (
    id_mda int NULL,
    dias int NULL
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
        INSERT INTO #terms (id_mda, dias)
        SELECT id_mda, dias
        FROM ' + QUOTENAME(@db) + N'.dbo.vtt_cat_prov
        WHERE id_prov <> 0;
    ';

    EXEC sys.sp_executesql @sql;

    FETCH NEXT FROM db_cursor INTO @db;
END;

CLOSE db_cursor;
DEALLOCATE db_cursor;

SELECT id_mda, COUNT(*) AS total
FROM #terms
GROUP BY id_mda
ORDER BY total DESC;

SELECT TOP (30) dias, COUNT(*) AS total
FROM #terms
GROUP BY dias
ORDER BY total DESC, dias;
