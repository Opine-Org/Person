<?php
namespace Person\Field;

class ActivityStream {
	public $services = [
		'layout'
	];

    public function render ($field) {
        $this->layout->
            app('bundles/Registration/app/collections/activity_stream')->
            layout('Person/collections/activity_stream')->
            args('streams', [
                'person_id' => $this->document['_id']
            ])->template()->
            write();
    }
}