<?php

class DBOverflowCheck {
    public $dbh        = null; // Database connection
    public $maxAllowed = null; // MySql field type limits
    public $columnData = null; // Analysis result

    public $proneToUnderflow = array(
        'tinyint',
        'smallint',
        'mediumint',
        'int',
        'bigint',
    );

    public $textTypes = array(
        'tinytext',
        'text',
        'mediumtext',
        'longtext',
        'varchar',
        'char',
    );

    public $numericTypes = array(
        'tinyint',
        'smallint',
        'mediumint',
        'int',
        'bigint',
        'utinyint',
        'usmallint',
        'umediumint',
        'uint',
        'ubigint',
    );

    function __construct() {

        # for numeric types
        # http://dev.mysql.com/doc/refman/5.7/en/integer-types.html
        # - the signed types min=-max
        # - unsigned types   min=0
        # 
        # for text types
        # http://dev.mysql.com/doc/refman/5.7/en/storage-requirements.html#idm140434164775232
        # http://stackoverflow.com/a/13506920/827519
        # http://stackoverflow.com/a/13932834/827519
        $this->maxAllowed = array(
            # numeric types
            'tinyint'    => "127",
            'utinyint'   => "255",
            'smallint'   => "32767",
            'usmallint'  => "65535",
            'mediumint'  => "8388607",
            'umediumint' => "16777215",
            'int'        => "2147483647",
            'uint'       => "4294967295",
            'bigint'     => "9223372036854775807",
            'ubigint'    => "18446744073709551615",
            # text types
            'char'       => 255,
            'varchar'    => 65535,
            'tinytext'   => (1<<8),
            'text'       => (1<<16),
            'mediumtext' => (1<<24),
            'longtext'   => (1<<32),
        );
    }

    function __destruct() {
        $dbh = null;
    }

    # get all column metadata
    function getColumnMetadata() {
        $query = file_get_contents('queries/fieldtop.sql');
        $sth = $this->dbh->query($query);
        $result = $sth->fetchAll(PDO::FETCH_ASSOC);
        $sth = null;

        return $result;
    }

    function connectDB($server, $user, $password, $dbName) {
        $this->dbh = new PDO("mysql:host=$server;dbname=$dbName", $user, $password);
    }

    # gets column minimum and maximum values
    function getMinMax($dbName,$tableName,$columnName,$dataType) {

        $query="";
        if(in_array($dataType, $this->numericTypes)) {
            $query="
            SELECT
                MIN(`$columnName`) AS _min,
                MAX(`$columnName`) AS _max
            FROM $dbName.$tableName ;
            ";
        } else if(in_array($dataType, $this->textTypes)) {
            $query="
            SELECT
                MIN(CHAR_LENGTH(`$columnName`)) AS _min,
                MAX(CHAR_LENGTH(`$columnName`)) AS _max
            FROM $dbName.$tableName ;
            ";
        } else {
            throw new Exception('getMinMax was not designed for this data type');
        };

        $sth = $this->dbh->query($query);
        $result = $sth->fetch(PDO::FETCH_ASSOC);
        $sth = null;

        return $result;
    }

    # extract column attributes
    function extractColumnWidth($columnType) {
        # this regex extracts the width of the column data type
        $regexColumnWidth = '/^.*\(([0-9]+(?:,[0-9]+)?)\).*$/';
        $regexSplitValues = '/[\s,\(\)]+/';
        preg_match($regexColumnWidth,$columnType,$rawWidthAttribs);

        # preg_match will put the entire string in [0] and the matches
        # will start at [1]
        if($rawWidthAttribs === NULL || count($rawWidthAttribs) < 2)
            return null;

        $widthAttribs = preg_split($regexSplitValues,$rawWidthAttribs[0]);

        return $widthAttribs;
    }

    # checks all columns for overflows
    function check() {
        $metadata = $this->getColumnMetadata();

        # put together all the required data
        $this->columnData = array();
        foreach($metadata as $idx => $cdata ) {
            $signed   = $cdata['signed'];
            $dataType = $cdata['DATA_TYPE'];
            $widthAttribs = $this->extractColumnWidth($cdata['COLUMN_TYPE']);
            if(!$signed)
                $dataType = "u$dataType";
            if(!array_key_exists($dataType, $this->maxAllowed))
                continue;

            $minmax = $this->getMinMax($cdata['TABLE_SCHEMA'],$cdata['TABLE_NAME'],$cdata['COLUMN_NAME'],$dataType);
            $minUsed  = $minmax['_min'];
            $maxUsed  = $minmax['_max'];

            $maxValueAllowed =  $this->maxAllowed[$dataType];
            $minValueAllowed = -$this->maxAllowed[$dataType];

            # for text fields, the maximum allowed is the width attribute in the column definition
            if(in_array($dataType, $this->textTypes) && $widthAttribs !== NULL) {
                $maxValueAllowed = $widthAttribs[1];
            };

            $toOverflow  = (floatval($maxUsed)/floatval($maxValueAllowed)) * 100.0;
            $toUnderflow = (floatval($minUsed)/floatval($minValueAllowed)) * 100.0;


            /*
               if toUnderflow is negative, the min value must be
               positive, and that's a very low risk of underflow.
               to make the report more consistent and clear,
               we set this to 0 
            */
            if($toUnderflow <= 0) {
                $toUnderflow = 0;
            };
            
            // analog situation for toOverflow
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

            array_push($this->columnData, $column_data);
        };

        # sort them by closeness maximum value
        uasort(
            $this->columnData,
            function($a,$b) {
                return $a['toOverflow'] < $b['toOverflow'];
            }
        );

    }

    function show($mode)
    {
        if ($mode=='html')
        {
            print('<style>td {padding-right: 2em} tr:hover {background: #ffff80} th {text-align: left} </style><table>');
            print('<tr><th>Field</th><th>Min</th><th>Max</th></tr>');
        }
        if ($mode=='cli')
        {
            printf("%-60s  %8s  %9s\n", 'Field','Min','Max');
        }
        foreach($this->columnData as $idx => $cdata) {
            $formattedName = sprintf('%s.%s.%s',$cdata['TABLE_SCHEMA'],$cdata['TABLE_NAME'],$cdata['COLUMN_NAME']);
            $toOverflow  = $cdata['toOverflow'];
            $toUnderflow = $cdata['toUnderflow'];
            $dataType    = $cdata['dataType'];
            if ($mode=='cli' ) $this->showFieldCli ($formattedName,$toOverflow,$toUnderflow,$dataType);
            if ($mode=='html') $this->showFieldHtml($formattedName,$toOverflow,$toUnderflow,$dataType);
        };
        if ($mode=='html') print '</table>';
    }

    function showFieldCli($formattedName,$toOverflow,$toUnderflow,$dataType)
    {
            $toOverflow  = sprintf("%3.4f", $toOverflow);
            $toUnderflow = sprintf("%3.4f", $toUnderflow);
            if(!in_array($dataType, $this->proneToUnderflow)) $toUnderflow='n/a';
            printf("%-60s %8s%%  %8s%%\n", $formattedName, $toUnderflow, $toOverflow);
    }

    function showFieldHtml($formattedName,$toOverflow,$toUnderflow,$dataType)
    {
            $toOverflow  = sprintf("%3.4f", $toOverflow);
            $toUnderflow = sprintf("%3.4f", $toUnderflow);
            if(!in_array($dataType, $this->proneToUnderflow)) $toUnderflow='-';
            printf("<tr><td>%s</td><td>%s</td><td>%s</td></tr>\n", $formattedName, $toUnderflow, $toOverflow);
    }


}
