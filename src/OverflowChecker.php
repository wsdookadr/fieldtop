<?php

namespace FieldTop;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\OutputInterface;

class OverflowChecker
{
    /**
     * @var \PDO|null
     */
    protected $dbh = null; // Database connection

    /**
     * MySQL field type limits
     *
     * @var array|null
     */
    protected $maxAllowed = null;

    /**
     * @var array
     */
    protected $proneToUnderflow = array(
        'tinyint',
        'smallint',
        'mediumint',
        'int',
        'bigint',
    );

    /**
     * @var array
     */
    protected $textTypes = array(
        'tinytext',
        'text',
        'mediumtext',
        'longtext',
        'varchar',
        'char',
    );

    /**
     * @var array
     */
    protected $numericTypes = array(
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

    protected $database;

    public function __construct()
    {
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
            'tinyint' => "127",
            'utinyint' => "255",
            'smallint' => "32767",
            'usmallint' => "65535",
            'mediumint' => "8388607",
            'umediumint' => "16777215",
            'int' => "2147483647",
            'uint' => "4294967295",
            'bigint' => "9223372036854775807",
            'ubigint' => "18446744073709551615",
            # text types
            'char' => 255,
            'varchar' => 65535,
            'tinytext' => (1 << 8),
            'text' => (1 << 16),
            'mediumtext' => (1 << 24),
            'longtext' => (1 << 32),
        );
    }

    /**
     * @param mixed $database
     */
    public function setDatabase($database)
    {
        $this->database = $database;
    }

    /**
     * Returns all column metadata
     *
     * @return array
     */
    protected function getColumnMetadata()
    {
        $query = file_get_contents(__DIR__ . '/../meta/fieldtop.sql');

        $databaseLimitation = '';
        if ($this->database) {
            $databaseLimitation = "AND TABLE_SCHEMA = '{$this->database}'";
        }

        $query = str_replace('__DATABASE__LIMITATION__', $databaseLimitation, $query);
        $sth = $this->dbh->query($query);
        $result = $sth->fetchAll(\PDO::FETCH_ASSOC);
        $sth = null;

        return $result;
    }

    /**
     * Connects to the database.
     *
     * @param string $user
     * @param string $password
     * @param string $dbName
     */
    public function connectDB($user, $password, $dbName = 'information_schema')
    {
        $this->dbh = new \PDO("mysql:host=localhost;dbname=$dbName", $user, $password);
    }

    /**
     * Returns column minimum and maximum values
     *
     * @param string $dbName
     * @param string $tableName
     * @param string $columnName
     * @param string $dataType
     * @return mixed
     *
     * @throws \Exception
     */
    protected function getMinMax($dbName, $tableName, $columnName, $dataType)
    {
        # check parameters
        $validNamePattern = '/^[a-zA-Z0-9_\-\ ]+$/';
        if (!preg_match($validNamePattern, $tableName)) {
            throw new \Exception(sprintf('Invalid table name: %s in db %s', $tableName, $dbName));
        };
        if (!preg_match($validNamePattern, $dbName)) {
            throw new \Exception('Invalid database name: '. $dbName);
        };
        if (!preg_match($validNamePattern, $columnName)) {
            printf("%s\n", $columnName);
            throw new \Exception('Invalid column name: ' . $columnName);
        };

        $query = "";
        if (in_array($dataType, $this->numericTypes)) {
            $query = "
                    SELECT
                        MIN(:columnName) AS _min,
                        MAX(:columnName) AS _max
                    FROM :table;
                    ";
        } else {
            if (in_array($dataType, $this->textTypes)) {
                $query = "
                    SELECT
                        MIN(CHAR_LENGTH(:columnName)) AS _min,
                        MAX(CHAR_LENGTH(:columnName)) AS _max
                    FROM :table;
                    ";
            } else {
                throw new \Exception('getMinMax was not designed for this data type');
            }
        };

        $query = str_replace([':columnName', ':table'], ["`$columnName`", "`$dbName`.`$tableName`"], $query);
        $stmt = $this->dbh->query($query);

        if (false === $stmt) {
            throw new \RuntimeException(sprintf('Query failed. %s: %s', $this->dbh->errorInfo()[0], $this->dbh->errorInfo()[2]));
        }

        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Extracts and returns column attributes
     *
     * @param string $columnType
     * @return array|null
     */
    protected function extractColumnWidth($columnType)
    {
        # this regex extracts the width of the column data type
        $regexColumnWidth = '/^.*\(([0-9]+(?:,[0-9]+)?)\).*$/';
        $regexSplitValues = '/[\s,\(\)]+/';
        preg_match($regexColumnWidth, $columnType, $rawWidthAttribs);

        # preg_match will put the entire string in [0] and the matches
        # will start at [1]
        if ($rawWidthAttribs === null || count($rawWidthAttribs) < 2) {
            return null;
        }

        $widthAttribs = preg_split($regexSplitValues, $rawWidthAttribs[0]);

        return $widthAttribs;
    }

    /**
     * Checks all columns for overflows
     *
     * @return array
     * @throws \Exception
     */
    public function check()
    {
        $metadata = $this->getColumnMetadata();

        # put together all the required data
        $columnData = array();
        foreach ($metadata as $idx => $cdata) {
            $signed = $cdata['signed'];
            $dataType = $cdata['DATA_TYPE'];
            $widthAttribs = $this->extractColumnWidth($cdata['COLUMN_TYPE']);
            if (!$signed) {
                $dataType = "u$dataType";
            }
            if (!array_key_exists($dataType, $this->maxAllowed)) {
                continue;
            }

            $minmax = $this->getMinMax($cdata['TABLE_SCHEMA'], $cdata['TABLE_NAME'], $cdata['COLUMN_NAME'], $dataType);
            $minUsed = $minmax['_min'];
            $maxUsed = $minmax['_max'];

            $maxValueAllowed = $this->maxAllowed[$dataType];
            $minValueAllowed = -$this->maxAllowed[$dataType];

            # for text fields, use width attribute in the column definition
            # as the maximum allowed 
            if (in_array($dataType, $this->textTypes) && $widthAttribs !== null) {
                $maxValueAllowed = $widthAttribs[1];
            };

            if (abs($maxValueAllowed) === 0) {
                $toOverflow = 100;
            } else {
                $toOverflow = number_format(($maxUsed/$maxValueAllowed) * 100, 4);
            }

            if (abs($minValueAllowed) === 0) {
                $toUnderflow = 100;
            } else {
                $toUnderflow = number_format(($minUsed/$minValueAllowed) * 100, 4);
            }

            # if toUnderflow is negative, the min value must be
            # positive, and that's a very low risk of underflow.
            # to make the report more consistent and clear,
            # we set this to 0
            if ($toUnderflow <= 0) {
                $toUnderflow = 0;
            };
            # analog situation for toOverflow
            if ($toOverflow <= 0) {
                $toOverflow = 0;
            };

            $column_data = array(
                'dataType' => $dataType,
                'maxAllowed' => $maxValueAllowed,
                'minAllowed' => $minValueAllowed,
                'toOverflow' => $toOverflow,
                'toUnderflow' => $toUnderflow,
                'TABLE_SCHEMA' => $cdata['TABLE_SCHEMA'],
                'TABLE_NAME' => $cdata['TABLE_NAME'],
                'COLUMN_NAME' => $cdata['COLUMN_NAME'],
                'widthAttribs' => $widthAttribs,
            );

            array_push($columnData, $column_data);
        };

        # sort them by closeness maximum value
        uasort(
            $columnData,
            function ($a, $b) {
                return $a['toOverflow'] < $b['toOverflow'];
            }
        );

        return $columnData;
    }

    /**
     * Checks and prints the overflow information.
     */
    public function showHTML()
    {
        print('<style>td {padding-right: 2em} tr:hover {background: #ffff80} th {text-align: left} </style><table>');
        print('<tr><th>Column</th><th>Min</th><th>Max</th></tr>');

        $columnData = $this->check();
        foreach ($columnData as $idx => $cdata) {
            $formattedName = sprintf('%s.%s.%s', $cdata['TABLE_SCHEMA'], $cdata['TABLE_NAME'], $cdata['COLUMN_NAME']);
            $toOverflow = $cdata['toOverflow'];
            $toUnderflow = $cdata['toUnderflow'];
            $dataType = $cdata['dataType'];

            $this->showFieldHtml($formattedName, $toOverflow, $toUnderflow, $dataType);
        };

        print '</table>';
    }
    /**
     * Checks and prints the overflow information.
     */
    public function showCLI(OutputInterface $output, $max = 100)
    {
        $columnData = $this->check();
        $total = count($columnData);
        $columnData = array_slice($columnData, 0, $max);

        $output->writeln(sprintf('<info>Shows %d of %d. Use --max=2000 to see more</info>', count($columnData), $total));
        $table = new Table($output);
        $table->setHeaders(['Column name', 'overflow', 'underflow']);

        foreach ($columnData as $idx => $cdata) {
            $formattedName = sprintf('%s.%s.%s', $cdata['TABLE_SCHEMA'], $cdata['TABLE_NAME'], $cdata['COLUMN_NAME']);

            $toOverflow = sprintf("%3.4f", $cdata['toOverflow']);
            $toUnderflow = sprintf("%3.4f", $cdata['toUnderflow']);
            $dataType = $cdata['dataType'];

            if (!in_array($dataType, $this->proneToUnderflow)) {
                $toUnderflow = 'n/a';
            }

            $table->addRow([
                $formattedName,
                $toUnderflow,
                $toOverflow
            ]);
        };

        $table->render();
    }

    function showFieldHtml($formattedName, $toOverflow, $toUnderflow, $dataType)
    {
        $toOverflow = sprintf("%3.4f", $toOverflow);
        $toUnderflow = sprintf("%3.4f", $toUnderflow);
        if (!in_array($dataType, $this->proneToUnderflow)) {
            $toUnderflow = '-';
        }
        printf("<tr><td>%s</td><td>%s</td><td>%s</td></tr>\n", $formattedName, $toUnderflow, $toOverflow);
    }
}
