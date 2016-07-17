SELECT
    b.COLUMN_NAME,
    b.COLUMN_TYPE,
    b.DATA_TYPE,
    b.signed,
    b.width,
    a.TABLE_NAME,
    a.TABLE_SCHEMA
FROM (
    -- get all tables
    SELECT
    TABLE_NAME, TABLE_SCHEMA
    FROM information_schema.tables
    WHERE 
    TABLE_TYPE IN ('BASE TABLE', 'VIEW') AND
    TABLE_SCHEMA NOT IN ('mysql', 'performance_schema')
) a
JOIN (
    -- get information about columns types
    SELECT
    TABLE_NAME,
    COLUMN_NAME,
    COLUMN_TYPE,
    TABLE_SCHEMA,
    DATA_TYPE,
    (LOWER(COLUMN_TYPE) REGEXP '.* signed.*') AS signed,
    CAST(
      -- gets width attribute of numeric data type
      (
          SUBSTR(
          COLUMN_TYPE,
          POSITION('(' in COLUMN_TYPE) + 1,
          POSITION(')' in COLUMN_TYPE) + 1 - POSITION('(' in COLUMN_TYPE)
          )
      )
      AS UNSIGNED
    ) AS width
    FROM information_schema.columns
) b ON a.TABLE_NAME = b.TABLE_NAME AND a.TABLE_SCHEMA = b.TABLE_SCHEMA
ORDER BY a.TABLE_SCHEMA DESC;
