<?php
/**
 * Opine\Person
 *
 * Copyright (c)2013, 2014 Ryan Mahoney, https://github.com/Opine-Org <ryan@virtuecenter.com>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */
namespace Opine;

class Person {
    private $db;
    private $id;
    private $current = false;
    private $config;

    public function __construct ($db, $config) {
        $this->db = $db;
        $this->config = $config;
        if (isset($_SESSION['user']) && isset($_SESSION['user']['_id'])) {
            $this->current = $_SESSION['user']['_id'];
        }
    }

    public function available () {
        if (isset($_SESSION['user']) && isset($_SESSION['user']['_id'])) {
            $this->current = $_SESSION['user']['_id'];
            return true;
        }
        return false;
    }

    public function create ($attributes) {
        $salt = $this->config->auth['salt'];
        if (isset($attributes['password'])) {
            $attributes['password'] = sha1($salt . 'password');
        } else {
            $attributes['password'] = sha1($salt . uniqid());
        }
        if (isset($attributes['email'])) {
            $attributes['email'] = strtolower(trim($attributes['email']));
        }
        $id = new \MongoId();
        $attributes['_id'] = $id;
        $dbURI = 'users:' . (string)$id;
        $attributes['created_date'] = new \MongoDate(strtotime('now'));
        $attributes['_id'] = $id;
        try {
            $this->db->documentStage($dbURI, $attributes)->upsert();
        } catch (\Exception $e) {
            return 'Error: ' . $e->getMessage();
        }
        $_SESSION['user'] = $attributes;
        $this->current = (string)$id;
        return true;
    }

    public function findById ($id) {
        $check = $this->db->collection('users')->findOne(['_id' => $this->db->id($id)]);
        if (isset($check['_id'])) {
            $this->current = $check['_id'];
            return $this->current;
        }
        return false;
    }

    public function findByEmail ($email) {
        $check = $this->db->collection('users')->findOne(['email' => strtolower(trim($email))]);
        if (isset($check['_id'])) {
            $this->current = $check['_id'];
            return $this->current;
        }
        return false;
    }

    public function groupJoin ($group) {
        if ($this->current === false) {
            return false;
        }
        $this->operation(['$addToSet' => ['groups' => $group]]);
    }

    private function operation ($operation) {
        $this->db->collection('users')->update(['_id' => $this->db->id($this->current)], $operation);
    }

    public function groupLeave ($group) {
        if ($this->current === false) {
            return false;
        }
        $this->operation(['$pull' => ['groups' => $group]]);
    }

    public function recordAdd ($dbURI, $description, $unique=false, $override=true) {
        $foundDbURI = false;
        if ($unique == true && $this->recordCheck($dbURI, $foundDbURI) === true) {
            if ($override) {
                $this->operation(['$pull' => ['records.dbURI' => $foundDbURI]]);
            } else {
                return false;
            }
        }
        $this->operation(['$push' => ['records' => [
            '_id' => new \MongoId(),
            'dbURI' => $dbURI,
            'description' => $description,
            'created_date' => new \MongoDate(strtotime('now'))
        ]]]);
    }

    public function recordCheck ($dbURI) {
        $parts = explode(':', $dbURI);
        array_pop($parts);
        $dbURI = implode(':', $parts);
        $result = $this->db->collection('users')->findOne([
            '_id' => $this->db->id($this->current),
            'records.dbURI' => [
                '$regex' => '/^' . $dbURI . '/'
            ]
        ]);
        if (isset($result['_id'])) {
            return true;
        }
        return false;
    }

    public function commentAdd ($id) {

    }

    public function attributesSet (Array $attributes) {

    }

    public function passwordSet($password) {

    }

    public function passwordResetRequest () {

    }

    public function addressSet(Array $address) {

    }

    public function photoSet(Array $image) {

    }

    //notes
    public function noteAdd ($message) {

    }

    //activities
    public function activityAdd($type, $message, $date=false) {

    }

    //classifications
    public function classify ($tag) {

    }

    public function declassify ($tag) {

    }
}