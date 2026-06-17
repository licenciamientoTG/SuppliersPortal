SET NOCOUNT ON;

CREATE TABLE #profile (
    database_name sysname NOT NULL,
    total_rows int NOT NULL,
    valid_rfc_rows int NOT NULL,
    empty_rfc_rows int NOT NULL,
    empty_email_rows int NOT NULL,
    empty_phone_rows int NOT NULL,
    empty_address_rows int NOT NULL,
    empty_contact_rows int NOT NULL,
    empty_cp_rows int NOT NULL,
    empty_cta_rows int NOT NULL,
    empty_clabe_rows int NOT NULL
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
        INSERT INTO #profile (
            database_name,
            total_rows,
            valid_rfc_rows,
            empty_rfc_rows,
            empty_email_rows,
            empty_phone_rows,
            empty_address_rows,
            empty_contact_rows,
            empty_cp_rows,
            empty_cta_rows,
            empty_clabe_rows
        )
        SELECT
            N''' + REPLACE(@db, '''', '''''') + N''',
            COUNT(*),
            SUM(CASE WHEN LEN(REPLACE(REPLACE(REPLACE(UPPER(LTRIM(RTRIM(ISNULL(rfc, '''')))), ''-'', ''''), '' '', ''''), ''.'', '''')) BETWEEN 12 AND 13 THEN 1 ELSE 0 END),
            SUM(CASE WHEN NULLIF(LTRIM(RTRIM(ISNULL(rfc, ''''))), '''') IS NULL THEN 1 ELSE 0 END),
            SUM(CASE WHEN NULLIF(LTRIM(RTRIM(ISNULL(email, ''''))), '''') IS NULL THEN 1 ELSE 0 END),
            SUM(CASE WHEN NULLIF(LTRIM(RTRIM(REPLACE(REPLACE(REPLACE(ISNULL(tel1, ''''), ''('', ''''), '')'', ''''), ''-'', ''''))), '''') IS NULL THEN 1 ELSE 0 END),
            SUM(CASE WHEN NULLIF(LTRIM(RTRIM(ISNULL(dir1, ''''))), '''') IS NULL THEN 1 ELSE 0 END),
            SUM(CASE WHEN NULLIF(LTRIM(RTRIM(COALESCE(NULLIF(Contacto1, ''''), NULLIF(nombre, ''''), NULLIF(nom1, ''''), ''''))), '''') IS NULL THEN 1 ELSE 0 END),
            SUM(CASE WHEN NULLIF(LTRIM(RTRIM(ISNULL(cp, ''''))), '''') IS NULL THEN 1 ELSE 0 END),
            SUM(CASE WHEN NULLIF(LTRIM(RTRIM(ISNULL(cta, ''''))), '''') IS NULL THEN 1 ELSE 0 END),
            SUM(CASE WHEN NULLIF(LTRIM(RTRIM(ISNULL(clabe, ''''))), '''') IS NULL THEN 1 ELSE 0 END)
        FROM ' + QUOTENAME(@db) + N'.dbo.vtt_cat_prov
        WHERE id_prov <> 0;
    ';

    EXEC sys.sp_executesql @sql;

    FETCH NEXT FROM db_cursor INTO @db;
END;

CLOSE db_cursor;
DEALLOCATE db_cursor;

SELECT *
FROM #profile
ORDER BY database_name;

SELECT
    SUM(total_rows) AS total_rows,
    SUM(valid_rfc_rows) AS valid_rfc_rows,
    SUM(empty_rfc_rows) AS empty_rfc_rows,
    SUM(empty_email_rows) AS empty_email_rows,
    SUM(empty_phone_rows) AS empty_phone_rows,
    SUM(empty_address_rows) AS empty_address_rows,
    SUM(empty_contact_rows) AS empty_contact_rows,
    SUM(empty_cp_rows) AS empty_cp_rows,
    SUM(empty_cta_rows) AS empty_cta_rows,
    SUM(empty_clabe_rows) AS empty_clabe_rows
FROM #profile;
