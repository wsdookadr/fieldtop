CREATE DATABASE IF NOT EXISTS test3;
USE test3;
DROP TABLE music;
CREATE TABLE IF NOT EXISTS music (
    pop         INT,
    band        VARCHAR(300),
    ttext       TEXT
);
TRUNCATE TABLE music;
-- Insert test data into the table
INSERT INTO music (pop, band, ttext) VALUES( 1319548814 , 'Red Hot Chilli Peppers',             '');
INSERT INTO music (pop, band, ttext) VALUES(-1300000000 , 'Band',                               '');
INSERT INTO music (pop, band, ttext) VALUES(           0,           '',  REPEAT('X', (1<<16)-1000));
INSERT INTO music (pop, band, ttext) VALUES( 1319548814 , 'Pink Floyd',                         '');
