<?php
namespace Person\Field;

class ActivityStream {
	public $services = [
		'layout'
	];

    public function render ($field) {
        $this->layout->
            app('Person/collections/user_activities')->
            layout('Person/collections/user_activities')->
            args('streams', [
                'person_id' => $this->document['_id']
            ])->
            write();
    }
}