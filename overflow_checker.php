<?php

if (php_sapi_name() !== "cli")
    echo "<pre>";

include('overflow_checker_config.php');

$o = new DBOverflowCheck();
$o->connectDB($userPass['user'],$userPass['pass'],'information_schema');
$o->check();

if (php_sapi_name() !== "cli")
    echo "</pre>";

class DBOverflowCheck {
    public $dbh;

    # http://dev.mysql.com/doc/refman/5.7/en/integer-types.html
    public $maxValues = array(
        'tinyint'    => 127,
        'utinyint'   => 255,
        'smallint'   => 32767,
        'usmallint'  => 65535,
        'mediumint'  => 8388607,
        'umediumint' => 16777215,
        'int'        => 2147483647,
        'uint'       => 4294967295,
        'bigint'     => 9223372036854775807,
        'ubigint'    => 18446744073709551615,
    );

    function __construct() {
    }

    function __destruct() {
        $dbh = null;
    }

    # get all column metadata
    function getColumnMetadata() {
        $query = file_get_contents('overflow_checker.sql');
        $sth = $this->dbh->query($query);
        $result = $sth->fetchAll(PDO::FETCH_ASSOC);
        $sth = null;

        return $result;
    }

    function connectDB($user, $password, $dbName) {
        $this->dbh = new PDO("mysql:host=localhost;dbname=$dbName", $user, $password);
    }

    # gets column minimum and maximum values
    function getMinMax($dbName,$tableName,$columnName) {

        # check parameters
        $validNamePattern = '/^[a-zA-Z0-9_\-]+$/';
        if(!preg_match($validNamePattern,$tableName)) {
            throw new Exception('Invalid table name');
        };
        if(!preg_match($validNamePattern,$dbName)) {
            throw new Exception('Invalid database name');
        };
        if(!preg_match($validNamePattern,$columnName)) {
            throw new Exception('Invalid column name');
        };

        $query = "
            SELECT
                MIN($columnName) AS _min,
                MAX($columnName) AS _max
            FROM $dbName.$tableName ;
        ";

        $sth = $this->dbh->query($query);
        //print_r($sth);
        $result = $sth->fetch(PDO::FETCH_ASSOC);
        $sth = null;

        return $result;
    }

    # checks all columns for overflows
    function check() {
        $metadata = $this->getColumnMetadata();

        # put together all the required data
        $all_column_data = array();
        foreach($metadata as $idx => $cdata ) {
            $maxKey = $cdata['DATA_TYPE'];
            if(!$cdata['signed'])
                $maxKey = "u$maxKey";
            if(!array_key_exists($maxKey, $this->maxValues))
                continue;

            $minmax = $this->getMinMax($cdata['TABLE_SCHEMA'],$cdata['TABLE_NAME'],$cdata['COLUMN_NAME']);
            $maxUsed  = $minmax['_max'];
            $maxField = $this->maxValues[$maxKey];
            $pUsed = ($maxUsed / $maxField) * 100.0;

            $column_data = array(
                'maxUsed'       => $maxUsed,
                'maxField'      => $maxField,
                'pUsed'         => $pUsed,
                'TABLE_SCHEMA'  => $cdata['TABLE_SCHEMA'],
                'TABLE_NAME'    => $cdata['TABLE_NAME'],
                'COLUMN_NAME'   => $cdata['COLUMN_NAME'],
            );

            array_push($all_column_data, $column_data);
        };

        # sort them by closeness to the maximum value for that data type
        uasort(
            $all_column_data,
            function($a,$b) {
                return $a['pUsed'] < $b['pUsed'];
            });

        # print all the data
        foreach($all_column_data as $idx => $cdata) {
            $formattedName = $cdata['TABLE_SCHEMA'].'.'.$cdata['TABLE_NAME'].'.'.$cdata['COLUMN_NAME'];
            $pUsed = $cdata['pUsed'];
            printf("%-60s %-.4f %% used\n", $formattedName, $pUsed);
        };
    }

}

