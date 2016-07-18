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
    # for the signed types min=-max
    # for unsigned types   min=0
    public $maxAllowed = array(
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

    public $proneToUnderflow = array(
        'tinyint',
        'smallint',
        'mediumint',
        'int',
        'bigint',
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

    # extract column attributes
    function extractColumnWidth($columnType) {
        # this method is not yet being used.

        # this regex extracts the width of the column data type
        $regexColumnWidth = '/^.*\(([0-9]+(?:,[0-9]+)?)\).*$/';
        preg_match($regexColumnWidth,$columnType,$rawWidthAttribs);

        return $rawWidthAttribs;
    }

    # checks all columns for overflows
    function check() {
        $metadata = $this->getColumnMetadata();

        # put together all the required data
        $all_column_data = array();
        foreach($metadata as $idx => $cdata ) {
            $signed   = $cdata['signed'];
            $dataType = $cdata['DATA_TYPE'];
            $widthAttribs = $this->extractColumnWidth($cdata['COLUMN_TYPE']);
            if(!$signed)
                $dataType = "u$dataType";
            if(!array_key_exists($dataType, $this->maxAllowed))
                continue;

            $minmax = $this->getMinMax($cdata['TABLE_SCHEMA'],$cdata['TABLE_NAME'],$cdata['COLUMN_NAME']);
            $minUsed  = $minmax['_min'];
            $maxUsed  = $minmax['_max'];

            $maxValueAllowed =  $this->maxAllowed[$dataType];
            $minValueAllowed = -$this->maxAllowed[$dataType];

            $toOverflow  = ($maxUsed / $maxValueAllowed) * 100.0;
            $toUnderflow = ($minUsed / $minValueAllowed) * 100.0;

            # if toUnderflow is negative, the min value must be
            # positive, and that's a very low risk of underflow.
            # to make the report more consistent and clear,
            # we set this to 0 
            if($toUnderflow <= 0) {
                $toUnderflow = 0;
            };
            # analog situation for toOverflow
            if($toOverflow <= 0) {
                $toOverflow = 0;
            };

            $column_data = array(
                'dataType'      => $dataType,
                'maxAllowed'    => $maxValueAllowed,
                'minAllowed'    => $minValueAllowed,
                'toOverflow'    => $toOverflow,
                'toUnderflow'   => $toUnderflow,
                'TABLE_SCHEMA'  => $cdata['TABLE_SCHEMA'],
                'TABLE_NAME'    => $cdata['TABLE_NAME'],
                'COLUMN_NAME'   => $cdata['COLUMN_NAME'],
                'widthAttribs'  => $widthAttribs,
            );

            array_push($all_column_data, $column_data);
        };

        # sort them by closeness maximum value
        uasort(
            $all_column_data,
            function($a,$b) {
                return $a['toOverflow'] < $b['toOverflow'];
            }
        );

        # print all the data
        printf("%-60s %-6s %-6s\n", 'column','overflow','underflow');
        foreach($all_column_data as $idx => $cdata) {
            $formattedName = sprintf('%s.%s.%s',$cdata['TABLE_SCHEMA'],$cdata['TABLE_NAME'],$cdata['COLUMN_NAME']);
            $toOverflow  = $cdata['toOverflow'];
            $toUnderflow = $cdata['toUnderflow'];
            $dataType    = $cdata['dataType'];

            if(in_array($dataType, $this->proneToUnderflow)) {
                printf("%-60s %-.4f%% %-.4f%%\n", $formattedName, $toOverflow, $toUnderflow);
            } else {
                printf("%-60s %-.4f%% %-6s\n"   , $formattedName, $toOverflow, 'N/A');
            };

        };
    }

}

