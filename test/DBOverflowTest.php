<?php
use PHPUnit\Framework\TestCase;
include(__DIR__ . '/../lib/fieldtop.php');

class DBOverflowTest extends TestCase
{
    protected function setUp(): void {

        $this->dbh = new PDO('mysql:host=127.0.0.1;dbname=', 'root', '');
        $query_schema = <<<QUERY
        CREATE DATABASE IF NOT EXISTS `community`;
        USE `community`;

        DROP TABLE IF EXISTS `posts`;
        CREATE TABLE `posts` (
                `id` int(11) NOT NULL,
                `text` varchar(1000) DEFAULT NULL,
                PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=latin1;

        DROP TABLE IF EXISTS `users`;
        CREATE TABLE `users` (
                `id` int(11) NOT NULL,
                `name` varchar(20) DEFAULT NULL,
                `karma` varchar(20) DEFAULT NULL,
                PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
QUERY;

        $query_data = <<<QUERY
        INSERT INTO users(name,karma) VALUES('abcdefghiiii','weotiqwetjqwiot');
        INSERT INTO users(name,karma) VALUES('abcdefiii','weqiowetqiw');
QUERY;

        $this->dbh->exec($query_schema);
        $this->dbh->exec($query_data);
    }

    public function testExceptions() {
        /*
           We had a case in a previous version where
           bcdiv(): Division by zero

           Maybe you could call this a regression test. Really we're just checking
           for any exceptions.
        */
        $this->obj = new DBOverflowCheck('cli');
        $this->obj->connectDB('127.0.0.1','root','','information_schema');
        $flag=false;
        try {
            $this->obj->check();
        } catch(Throwable $t) {
            $flag=true;
        };
        $this->assertEquals($flag, false, "No exception occured when calling ->check() method");
    }

    public function testBasic() {
        $this->o = new DBOverflowCheck('cli');
        $this->o->connectDB('127.0.0.1','root','','information_schema');
        $this->o->check();

        /* TODO */
        $this->assertEquals(true, true);
    }

    protected function tearDown(): void {
    }

}


