<?php
/*
 * @version .1
 */
namespace Person\Collection;

class ActivityStream {
    public $publishable = false;
    public $singular = 'activity';
    private $personFields = ['prefix', 'suffix', 'first_name', 'middle_name', 'last_name', 'email', 'phone'];

    public function chunk (&$rows) {
        $users = [];
        $details = [];
        foreach ($rows as $row) {
            $users[] = $this->db->id($row['user_id']);
        }
        $users = array_unique($users);
        $details = $this->db->fetchAllGrouped($this->db->collection('users')->find(
            ['_id' => ['$in' => $users]],
            $this->personFields
        ), '_id', $this->personFields, true);
        foreach ($rows as &$row) {
            $key = (string)$row['user_id'];
            if (!isset($details[$key])) {
                continue;
            }
            $row = array_merge($row, $details[$key]);
        }
    }
}