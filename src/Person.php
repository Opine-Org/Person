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

    public function current () {
        return $this->current;
    }

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

    public function password ($password) {
        $salt = $this->config->auth['salt'];
        return sha1($salt . $password);
    }

    public function create ($attributes) {
        if (isset($attributes['password'])) {
            $attributes['password'] = $this->password($attributes['password']);
        } else {
            $attributes['password'] = $this->password(uniqid());
        }
        if (isset($attributes['email'])) {
            $attributes['email'] = strtolower(trim($attributes['email']));
        }
        if (!isset($attributes['_id'])) {
            $attributes['_id'] = new \MongoId();
        } else {
            $attributes['_id'] = $this->db->id($attributes['_id']);
        }
        $dbURI = 'users:' . (string)$attributes['_id'];
        $attributes['created_date'] = new \MongoDate(strtotime('now'));
        try {
            $this->db->documentStage($dbURI, $attributes)->upsert();
        } catch (\Exception $e) {
            return 'Error: ' . $e->getMessage();
        }
        $_SESSION['user'] = $attributes;
        $this->current = (string)$attributes['_id'];
        return true;
    }

    public function findById ($id, $fields=[]) {
        $check = $this->db->collection('users')->findOne(['_id' => $this->db->id($id)], $fields);
        if (isset($check['_id'])) {
            $this->current = $check['_id'];
            return $check;
        }
        return false;
    }

    public function findByEmail ($email, $fields=[]) {
        $check = $this->db->collection('users')->findOne(['email' => strtolower(trim($email))], $fields);
        if (isset($check['_id'])) {
            $this->current = $check['_id'];
            return $check;
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

    public function recordAdd ($dbURI, $type, $description, $uniqueType=false, $override=false) {
        $foundDbURI = false;
        if ($uniqueType == true && $this->recordCheck($type, $foundDbURI) === true) {
            if ($override === true) {
                $this->operation(['$pull' => ['records' => ['dbURI' => $foundDbURI]]]);
            } else {
                return false;
            }
        }
        $this->operation(['$push' => ['records' => [
            '_id' => new \MongoId(),
            'dbURI' => $dbURI,
            'type' => $type,
            'description' => $description,
            'created_date' => new \MongoDate(strtotime('now'))
        ]]]);
    }

    private function recordCheck ($type, &$foundDbURI=false) {
        $result = $this->db->collection('users')->findOne([
            '_id' => $this->db->id($this->current),
            'records.type' => $type
        ], [
            'records' => [
                '$elemMatch' => [
                    'type' => $type
                ]
            ]
        ]);
        if (isset($result['records']) && count($result['records'] > 0) && isset($result['records'][0]['_id'])) {
            $foundDbURI = $result['records'][0]['dbURI'];
            return true;
        }
        return false;
    }

    public function attributesSet (Array $attributes) {
        $this->operation([
            '$set' => $attributes
        ]);
    }

    public function passwordSet($password) {
        $this->attributesSet([
            'password' => $this->password($password)
        ]);
    }

    public function photoSet(Array $image) {
        $this->attributesSet([
            'image' => $image
        ]);   
    }

    public function activityAdd($type, $description) {
        $activity = [
            '_id' => new \MongoId(),
            'type' => $type,
            'description' => $description,
            'created_date' => new \MongoDate(strtotime('now'))
        ];
        $this->operation(['$push' => ['activity' => $activity]]);
        $activity['user_id'] = $this->current;
        $this->db->collection('activity_stream')->save($activity);
    }

    public function classify ($tag) {
        if ($this->current === false) {
            return false;
        }
        $this->operation(['$addToSet' => ['classification_tags' => $tag]]);
    }

    public function declassify ($tag) {
        if ($this->current === false) {
            return false;
        }
        $this->operation(['$pull' => ['classification_tags' => $tag]]);
    }
}