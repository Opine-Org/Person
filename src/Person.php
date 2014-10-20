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

    public function availableFindOrCreate ($attributes) {
        $available = $this->available();
        if ($available === true) {
            return true;
        }
        if (!isset($attributes['email'])) {
            return false;
        }
        if ($this->findByEmail(strtolower($attributes['email'])) !== false) {
            return true;
        }
        return $this->create($attributes);
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
            $attributes['_id'] = $this->db->id();
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
        return $this;
    }

    public function passwordSet($password) {
        $this->attributesSet([
            'password' => $this->password($password)
        ]);
        return $this;
    }

    public function photoSet(Array $image) {
        $this->attributesSet([
            'image' => $image
        ]);
        return $this;
    }

    public function activityAdd($type, $description) {
        $activity = [
            '_id' => new \MongoId(),
            'type' => $type,
            'description' => $description,
            'created_date' => new \MongoDate(strtotime('now'))
        ];
        $activity['user_id'] = $this->current;
        $this->db->collection('activity_stream')->save($activity);
        return $this;
    }

    public function classify ($tag) {
        if ($this->current === false) {
            return false;
        }
        $this->operation(['$addToSet' => ['classification_tags' => $tag]]);
        return $this;
    }

    public function declassify ($tag) {
        if ($this->current === false) {
            return false;
        }
        $this->operation(['$pull' => ['classification_tags' => $tag]]);
        return $this;
    }

    public function addressBillingSet (array $address) {
        $this->addressValidate($address);
        $this->operation([
            '$set' => [
                'billing_address' => $address
            ]
        ]);
        $this->addressAdd($address);
        return $this;
    }

    public function addressAdd (array $address) {
        $this->addressValidate($address);
        $match = false;
        $user = $this->db->collection('users')->findOne(['_id' => $this->db->id($this->current)], ['addresses']);
        if (isset($user['addresses']) && is_array($user['addresses'])) {
            foreach ($user['addresses'] as $found) {
                if (
                        $this->prepMatch($found['city']) == $this->prepMatch($address['city']) && 
                        $this->prepMatch($found['address']) == $this->prepMatch($address['address']) &&
                        $this->prepMatch($found['zipcode']) == $this->prepMatch($address['zipcode'])
                ) {
                    $match = true;
                    break;
                }
            }
        }
        if ($match === false) {
            $this->db->documentStage('users:' . (string)$this->current . ':addresses:' . (string)$this->db->id(), $address)->upsert();
        }
        return $this;
    }

    private function prepMatch ($string) {
        return substr(strtolower(str_replace(['    ', '   ', '  '], ' ', trim($string))), 0, 5);
    }

    private function addressValidate (array $address) {
        $fields = ['address', 'city', 'state', 'zipcode'];
        foreach ($fields as $field) {
            if (!isset($address[$field]) || empty($address[$field])) {
                throw new AddressException('Address missing: ' . $field);
            }
        }
        return true;
    }

    public function sessionCheck (&$userId=false) {
        if (isset($_SESSION['user']) && isset($_SESSION['user']['_id'])) {
            $userId = $_SESSION['user']['_id'];
            return true;
        }
        return false;
    }

    private function findAndEstablishSession ($criteria) {
        $user = $this->db->collection('users')->findOne(
            $criteria, [
                '_id', 
                'email', 
                'first_name', 
                'last_name', 
                'groups', 
                'created_date',
                'image',
                'groups'
            ]);
        if (!isset($user['_id'])) {
            return false;
        }
        $_SESSION['user'] = $user;
        $this->db->collection('login_history')->save([
            'user_id' => $user['_id'],
            'created_date' => new \MongoDate(strtotime('now'))
        ]);
        return true;
    }

    public function login ($identity, $password, $identityField='email', $criteria=false) {
        if ($identityField == 'email') {
            $identity = trim(strtolower($identity));
        }
        if ($criteria === false) {
            $criteria = [
                $identityField => $identity,
                'password' => $this->passwordHash($password)
            ];
        }
        return $this->findAndEstablishSession($criteria);
    }

    public function loginByUserId ($userId) {
        $criteria = [
            '_id' => $this->db->id($userId)
        ];
        return $this->findAndEstablishSession($criteria);
    }

    public function permission ($group) {
        if (is_array($group)) {
            foreach ($group as $subgroup) {
                $result = $this->permission($subgroup);
                if ($result === true) {
                    return true;
                }
            }
            return false;
        }
        if (!isset($_SESSION['user']) || !isset($_SESSION['user']['groups'])) {
            return false;
        }
        $groups = array_map('strtolower', $_SESSION['user']['groups']);
        if (in_array('superadmin', $groups)) {
            return true;
        }
        if (in_array(strtolower($group), $groups)) {
            return true;
        }
        return false;
    }

    public function inGroupLike ($pattern) {
        if (!isset($_SESSION['user']) || !isset($_SESSION['user']['groups'])) {
            return false;
        }
        if (count(preg_grep($pattern, $_SESSION['user']['groups'])) > 0) {
            return true;
        }
        return false;
    }

    public function logout () {
        $_SESSION['user'] = [];
    }

    public function passwordHash ($password) {
        $config = $this->config->auth;
        return sha1($config['salt'] . $password);
    }

    public function passwordForgot ($email) {
        //validate user
        //enforce rate limit
        //generate token
        //email via topic
    }

    public function passwordChange ($id, $token, $password) {
        //validate token
        //change password, remove token
    }






}

class AddressException extends \Exception {}