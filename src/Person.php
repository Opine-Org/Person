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
use MongoDate;
use MongoId;
use Exception;
use Opine\Framework;

class Person {
    private $db;
    private $current = false;
    private $config;
    private $cache;
    private $fields;

    public function current () {
        return $this->current;
    }

    public function __construct ($db, $config, $cache) {
        $this->db = $db;
        $this->config = $config;
        $this->cache = $cache;
        $this->fields = ['_id', 'email', 'first_name', 'last_name', 'groups', 'created_date', 'image', 'api_token'];
    }

    public function available () {
        if ($this->current != false) {
            return true;
        }
        return false;
    }

    public function get () {
        return $this->current;
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
        $attributes['created_date'] = new MongoDate(strtotime('now'));
        $attributes['api_token'] = new MongoId();
        try {
            $this->db->documentStage($dbURI, $attributes)->upsert();
        } catch (Exception $e) {
            return 'Error: ' . $e->getMessage();
        }
        $this->establish($attributes);
        $this->setCache($attributes);
        return true;
    }

    public function findById ($id, Array $fields=[]) {
        $person = $this->db->collection('users')->findOne(['_id' => $this->db->id($id)], array_merge($this->fields, $fields));
        if (isset($person['_id'])) {
            $this->current = $person;
            return $person;
        }
        return false;
    }

    public function findByEmail ($email, $fields=[]) {
        $check = $this->db->collection('users')->findOne(['email' => strtolower(trim($email))], array_merge($this->fields, $fields));
        if (isset($check['_id'])) {
            $this->establish($check);
            return $check;
        }
        return false;
    }

    public function groups () {
        if ($this->current === false) {
            return false;
        }
        return $this->current['groups'];
    }

    public function groupJoin ($group) {
        if ($this->current === false) {
            return false;
        }
        $this->operation(['$addToSet' => ['groups' => $group]]);
    }

    public function groupLeave ($group) {
        if ($this->current === false) {
            return false;
        }
        $this->operation(['$pull' => ['groups' => $group]]);
    }

    private function operation ($operation) {
        $this->db->collection('users')->update(['_id' => $this->db->id($this->current['_id'])], $operation);
    }

    public function commit () {
        if (!isset($this->current['_id'])) {
            return false;
        }
        $user = $this->findById($this->current['_id']);
        $this->setCache($user);
        return true;
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
            '_id' => new MongoId(),
            'dbURI' => $dbURI,
            'type' => $type,
            'description' => $description,
            'created_date' => new MongoDate(strtotime('now'))
        ]]]);
    }

    private function recordCheck ($type, &$foundDbURI=false) {
        $result = $this->db->collection('users')->findOne([
            '_id' => $this->db->id($this->current['_id']),
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
            '_id' => new MongoId(),
            'type' => $type,
            'description' => $description,
            'created_date' => new MongoDate(strtotime('now'))
        ];
        $activity['user_id'] = $this->db->id($this->current['_id']);
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
        $user = $this->db->collection('users')->findOne(['_id' => $this->db->id($this->current['_id'])], ['addresses']);
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
            $this->db->documentStage('users:' . (string)$this->current['_id'] . ':addresses:' . (string)$this->db->id(), $address)->upsert();
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

    private function findAndEstablishSession ($criteria) {
        $user = $this->db->collection('users')->findOne(
            $criteria, $this->fields);
        if (!isset($user['_id'])) {
            return false;
        }
        $user['api_token'] = new MongoId();
        $this->establish($user);
        $this->attributesSet(['api_token' => $user['api_token']]);
        $this->setCache($user);
        $this->sessionSave();
        return true;
    }

    public function sessionSave ($provider='website') {
        $this->db->collection('sessions')->save([
            'provider' => $provider,
            'user_id' => $this->current['_id'],
            'api_token' => $this->current['api_token'],
            'created_date' => new MongoDate(strtotime('now')),
            'request_method' => isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : NULL,
            'request_uri' => isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : NULL,
            'referer' => (isset($_SERVER['HTTP_REFERER']) && !empty($_SERVER['HTTP_REFERER'])) ? $_SERVER['HTTP_REFERER'] : NULL,
            'remote_addr' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : NULL,
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : NULL
        ]);
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
        if ($this->current === false || !isset($this->current['groups'])) {
            return false;
        }
        if (is_array($group)) {
            foreach ($group as $subgroup) {
                $result = $this->permission($subgroup);
                if ($result === true) {
                    return true;
                }
            }
            return false;
        }
        $groups = array_map('strtolower', $this->current['groups']);
        if (in_array('superadmin', $groups)) {
            return true;
        }
        if (in_array(strtolower($group), $groups)) {
            return true;
        }
        return false;
    }

    public function inGroupLike ($pattern) {
        if ($this->current === false || !isset($this->current['groups'])) {
            return false;
        }
        if (count(preg_grep($pattern, $this->current['groups'])) > 0) {
            return true;
        }
        return false;
    }

    public function logout () {
        $this->cache->delete('person-' . $this->current['api_token']);
        $this->attributesSet(['api_token' => NULL]);
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

    public function establish (Array $value) {
        $this->current = $value;
    }

    public function findByApiToken ($apiToken, $noCache=false) {
        $person = false;
        if ($noCache === false) {
            $person = $this->cache->get('person-' . $apiToken);
            if ($person !== false) {
                return json_decode($person);
            }
        }
        $person = $this->db->collection('users')->findOne(['api_token' => $this->db->id($apiToken)], $this->fields);
        if (isset($person['_id'])) {
            return $person;
        }
        return false;
    }

    private function setCache (Array $person, $ttl=false) {
        $person['_id'] = (string)$person['_id'];
        $person['api_token'] = (string)$person['api_token'];
        Framework::keySet('user_id', $person['_id']);
        Framework::keySet('api_token', $person['api_token']);
        if (!is_int($ttl)) {
            $ttl = 60 * 60 * 3;
        }
        $this->cache->set('person-' . $person['api_token'], json_encode($person), $ttl);
    }

    public static function apiTokenFromRequest () {
        if (isset($_SERVER['api_token'])) {
            return $_SERVER['api_token'];
        }
        if (isset($_GET['api_token'])) {
            return $_GET['api_token'];
        }
        if (isset($_COOKIE['api_token'])) {
            return $_COOKIE['api_token'];
        }
        return false;
    }

    public static function groupTokenFromRequest () {
        if (isset($_SERVER['group_token'])) {
            return $_SERVER['group_token'];
        }
        if (isset($_GET['group_token'])) {
            return $_GET['group_token'];
        }
        if (isset($_COOKIE['group_token'])) {
            return $_COOKIE['group_token'];
        }
        return false;
    }

    public function idGet () {
        if ($this->current === false) {
            return false;
        }
        if (isset($this->current['_id'])) {
            return $this->current['_id'];
        }
    }

    public function ssoProviderAdd ($provider, $payload) {
        $result = $this->db->collection('users')->findOne([
            '_id' => $this->db->id($this->current['_id']),
            'providers.type' => $provider
        ], [
            'providers' => [
                '$elemMatch' => [
                    'type' => $provider
                ]
            ]
        ]);
        if (isset($result['providers']) && count($result['providers'] > 0) && isset($result['providers'][0]['_id'])) {
            $dbURI = $result['providers'][0]['dbURI'];
        } else {
            $dbURI = 'users:' . (string)$this->current['_id'] . ':providers:' . (string)$this->db->id();
        }

        $this->operation(['$push' => ['providers' => [
            '_id' => new MongoId(),
            'dbURI' => $dbURI,
            'type' => $provider,
            'payload' => (array)$payload,
            'created_date' => new MongoDate(strtotime('now'))
        ]]]);
    }
}

class AddressException extends Exception {}