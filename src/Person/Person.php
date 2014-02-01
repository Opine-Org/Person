<?php
namespace Person;

class Person {
	private $db;
	private $id;
	private $current = false;

	public function __construct ($db, $config) {
		$this->db = $db;
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

	public function create (Array $attributes) {
		$salt = $config->auth['salt'];
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
		$this->db->collection('users')->findOne(['email' => strtolower(trim($email))]);
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

	public function recordAdd ($dbURI, $description, $unique=false) {
		if ($unique == true) {
			if ($this->recordCheck($collection) === false) {

			}
		}
	}

	public function recordCheck ($collection) {

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