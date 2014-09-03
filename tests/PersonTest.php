<?php
namespace Opine;

class PersonTest extends \PHPUnit_Framework_TestCase {
    private $person;
    private $db;

    public function setup () {
        date_default_timezone_set('UTC');
        $root = __DIR__ . '/../public';
        $container = new Container($root, $root . '/../container.yml');
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
        $this->person->recordAdd($dbURI, 'membership', 'Memership Form');
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
        $id = new \MongoId();
        $recordId = new \MongoId();
        $dbURI = 'membership:' . (string)$recordId;
        $this->personCreate($id);
        for ($i=0; $i < 3; $i++) {
            $this->person->recordAdd($dbURI, 'membership', 'Memership Form', true);
        }
        $person = $this->person->findById($id);
        $count = 0;
        foreach ($person['records'] as $record) {
            if ($record['dbURI'] == $dbURI) {
                $count++;
            }
        }
        $single = false;
        if ($count == 1) {
            $single = true;
        }
        $this->assertTrue($single);
    }

    public function testPersonRecordAddUniqueOverride () {
        $id = new \MongoId();
        $this->personCreate($id);
        $this->person->recordAdd('membership:' . (string)new \MongoId(), 'membership', 'Memership Form', true);
        $this->person->recordAdd('membership:' . (string)new \MongoId(), 'membership', 'Memership Form 2', true, true);
        $person = $this->person->findById($id);
        $count = 0;
        foreach ($person['records'] as $record) {
            $count++;
        }
        $single = false;
        if ($count == 1) {
            $single = true;
        }
        $this->assertTrue($single);
        $replaced = false;
        foreach ($person['records'] as $record) {
            if ($record['description'] == 'Memership Form 2') {
                $replaced = true;
            }
        }
        $this->assertTrue($replaced);
    }

    public function testPersonSetAttribute () {
        $id = new \MongoId();
        $this->personCreate($id);
        $this->person->attributesSet([
            'first_name' => 'Tom'
        ]);
        $person = $this->person->findById($id);
        $matched = false;
        if ($person['first_name'] == 'Tom') {
            $matched = true;
        }
        $this->assertTrue($matched);
    }

    public function testPersonSetPhoto () {
        $id = new \MongoId();
        $this->personCreate($id);
        $image = [
            'name' => 'speaking.jpg',
            'type' => 'image/jpeg',
            'size' => '141034',
            'md5' => '6ff9a302741e3cad66b03bbd26c5ec25',
            'width' => '600',
            'height' => '400',
            'url' => 'http://ilyasah-site.virtuecenter.com/storage/2013-12-02-16/speaking.jpg'
        ];
        $this->person->photoSet($image);
        $person = $this->person->findById($id);
        $matched = false;
        if ($person['image']['url'] == $image['url']) {
            $matched = true;
        }
        $this->assertTrue($matched);   
    }

    public function testPersonActivityAdd () {
        $id = new \MongoId();
        $this->personCreate($id);
        $this->person->activityAdd('subscribe', 'Subscribed to mailing list');
        $person = $this->person->findById($id, ['activity']);
        $matched = false;
        if (isset($person['activity']) && count($person['activity']) == 1) {
            $matched = true;
        }
        $this->assertTrue($matched);
    }

    public function testPersonClassify () {
        $id = new \MongoId();
        $this->personCreate($id);
        $tag = 'Testing';
        $this->person->classify($tag);
        $person = $this->person->findById($id, ['classification_tags']);
        $found = false;
        if (in_array($tag, $person['classification_tags'])) {
            $found = true;
        }
        $this->assertTrue($found);
    }

    public function testPersonDeclassify () {
        $id = new \MongoId();
        $this->personCreate($id);
        $tag = 'Testing';
        $this->person->classify($tag);
        $this->person->declassify($tag);
        $person = $this->person->findById($id, ['classification_tags']);
        $found = false;
        if (in_array($tag, $person['classification_tags'])) {
            $found = true;
        }
        $this->assertFalse($found);
    }
}