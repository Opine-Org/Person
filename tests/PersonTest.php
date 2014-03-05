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

    public function testPersonNotAvailableInSession () {
        $result = $this->person->available();
        $this->assertFalse($result);
    }

    public function testPersonAvailableInSession () {
        $_SESSION['user'] = [
            '_id' => '5314f7553698bb5228b15cc2'
        ];
        $result = $this->person->available();
        $this->assertTrue($result);
        unset($_SESSION['user']);
    }

    private function personCreate ($id) {
        $this->person->create([
            '_id' => $id,
            'email' => 'test@unit.com',
            'password' => 'secret'
        ]);
    }

    public function testPersonCreate () {
        $id = new \MongoId();
        $this->personCreate($id);
        $same = false;
        if ((string)$id == (string)$this->person->current()) {
            $same = true;
        }
        $this->assertTrue($same);
    }

    public function testPersonNotFoundById () {
        $id = new \MongoId();
        $result = $this->person->findById($id);
        $this->assertFalse($result);
    }

    public function testPersonFoundById () {
        $id = new \MongoId();
        $this->personCreate($id);
        $person = $this->person->findById($id);
        $found = false;
        if ((string)$id == (string)$person['_id']) {
            $found = true;
        }
        $this->assertTrue($found);
    }

    public function testPersonPasswordCorrect () {
        $id = new \MongoId();
        $this->personCreate($id);
        $person = $this->person->findById($id);
        $password = $this->person->password('secret');
        $matched = false;
        if ($person['password'] == $password) {
            $matched = true;
        }
        $this->assertTrue($matched);
    }

    public function testPersonNotFoundByEmail () {
        $id = new \MongoId();
        $this->personCreate($id);
        $person = $this->person->findByEmail('nomatch@unit.com');
        $this->assertFalse($person);
    }

    public function testPersonFindByEmail () {
        $id = new \MongoId();
        $this->personCreate($id);
        $email = 'test@unit.com';
        $person = $this->person->findByEmail($email);
        $same = false;
        if ($email == $person['email']) {
            $same = true;
        }
        $this->assertTrue($same);
    }

    public function testPersonGroupJoin () {
        $id = new \MongoId();
        $this->personCreate($id);
        $group = 'Testing';
        $this->person->groupJoin($group);
        $person = $this->person->findById($id);
        $found = false;
        if (in_array($group, $person['groups'])) {
            $found = true;
        }
        $this->assertTrue($found);
    }

    public function testPersonGroupLeave () {
        $id = new \MongoId();
        $this->personCreate($id);
        $group = 'Testing';
        $this->person->groupJoin($group);
        $this->person->groupLeave($group);
        $person = $this->person->findById($id);
        $found = false;
        if (in_array($group, $person['groups'])) {
            $found = true;
        }
        $this->assertFalse($found);
    }

    public function testPersonGroupJoinUnique () {
        $id = new \MongoId();
        $this->personCreate($id);
        $group = 'Testing';
        $this->person->groupJoin($group);
        $this->person->groupJoin($group);
        $person = $this->person->findById($id);
        $matched = false;
        $count = count($person['groups']);
        if ($count == 1) {
            $matched = true;
        }
        $this->assertTrue($matched);
    }

    public function testPersonRecordAdd () {
        $id = new \MongoId();
        $recordId = new \MongoId();
        $dbURI = 'membership:' . (string)$recordId;
        $this->personCreate($id);
        $this->person->recordAdd($dbURI, 'Memership Form');
        $person = $this->person->findById($id);
        $found = false;
        foreach ($person['records'] as $record) {
            if ($record['dbURI'] == $dbURI) {
                $found = true;
            }
        }
        $this->assertTrue($found);
    }

    public function testPersonRecordAddUnique () {

    }
}