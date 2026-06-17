SET NOCOUNT ON;

CREATE TABLE #providers (
    source_database sysname NOT NULL,
    id_prov int NOT NULL,
    rfc_raw varchar(40) NULL,
    rfc_clean varchar(40) NULL,
    company_name varchar(128) NULL,
    email varchar(200) NULL,
    status tinyint NULL,
    tip_prov tinyint NULL
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
        INSERT INTO #providers (
            source_database,
            id_prov,
            rfc_raw,
            rfc_clean,
            company_name,
            email,
            status,
            tip_prov
        )
        SELECT
            N''' + REPLACE(@db, '''', '''''') + N''',
            id_prov,
            rfc,
            REPLACE(REPLACE(REPLACE(UPPER(LTRIM(RTRIM(ISNULL(rfc, '''')))), ''-'', ''''), '' '', ''''), ''.'', ''''),
            nom1,
            email,
            status,
            tip_prov
        FROM ' + QUOTENAME(@db) + N'.dbo.vtt_cat_prov
        WHERE id_prov <> 0;
    ';

    EXEC sys.sp_executesql @sql;

    FETCH NEXT FROM db_cursor INTO @db;
END;

CLOSE db_cursor;
DEALLOCATE db_cursor;

SELECT
    COUNT(*) AS source_rows,
    COUNT(DISTINCT rfc_clean) AS distinct_rfc,
    SUM(CASE WHEN LEN(rfc_clean) NOT BETWEEN 12 AND 13 THEN 1 ELSE 0 END) AS invalid_rfc_rows
FROM #providers;

SELECT TOP (20)
    rfc_clean,
    COUNT(*) AS appearances,
    COUNT(DISTINCT source_database) AS database_count,
    MIN(company_name) AS sample_company
FROM #providers
WHERE LEN(rfc_clean) BETWEEN 12 AND 13
GROUP BY rfc_clean
HAVING COUNT(*) > 1
ORDER BY COUNT(*) DESC, rfc_clean;

SELECT TOP (20)
    source_database,
    id_prov,
    rfc_raw,
    rfc_clean,
    company_name,
    email
FROM #providers
WHERE LEN(rfc_clean) NOT BETWEEN 12 AND 13
ORDER BY source_database, id_prov;

SELECT
    status,
    COUNT(*) AS total
FROM #providers
GROUP BY status
ORDER BY status;

SELECT
    tip_prov,
    COUNT(*) AS total
FROM #providers
GROUP BY tip_prov
ORDER BY tip_prov;
