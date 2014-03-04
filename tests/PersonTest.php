<?php
namespace Opine;

class PersonTest extends \PHPUnit_Framework_TestCase {
    private $person;
    private $db;

    public function setup () {
        date_default_timezone_set('America/New_York');
        $root = getcwd();
        $container = new Container($root, $root . '/container.yml');
        $this->person = $container->person;
        $this->db = $container->db;
    }

    public function testPerson () {
        $this->assertFalse(false);
    }
}