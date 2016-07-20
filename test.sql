CREATE DATABASE IF NOT EXISTS test3;
USE test3;
DROP TABLE music;
CREATE TABLE IF NOT EXISTS music (
    pop         INT,
    band        VARCHAR(300),
    ttext       TEXT,
    ltext       VARCHAR(4),
    lint        INT(4)
);
TRUNCATE TABLE music;

-- insert test data into the table

-- in-range data
INSERT INTO music (pop, band, ttext) VALUES( 1319548814 , 'Red Hot Chilli Peppers',             '');
INSERT INTO music (pop, band, ttext) VALUES(-1300000000 , 'Band',                               '');
INSERT INTO music (pop, band, ttext) VALUES(           0,           '',  REPEAT('X', (1<<16)-1000));
INSERT INTO music (pop, band, ttext) VALUES( 1319548814 , 'Pink Floyd',                         '');

--
-- this data will overflow
-- (numeric types are not truncated, it's up to the application 
--  to deal with the display width
-- http://dev.mysql.com/doc/refman/5.7/en/numeric-type-attributes.html)
--
-- (text types are truncated to fit
--  http://dev.mysql.com/doc/refman/5.7/en/blob.html)
-- 
INSERT INTO music (pop, band, ttext, ltext) VALUES( 20         , 'Iggy Azalea', '', 'ABCDEFGH');
INSERT INTO music (pop, band, ttext, ltext, lint) VALUES(0, '','', '',99999);
