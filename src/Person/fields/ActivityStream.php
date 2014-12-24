<?php
namespace Person\Field;

class ActivityStream
{
    public $services = [
        'layout',
    ];

    public function render($field)
    {
        $this->layout->
            config('Person/collections/user_activities')->
            container('Person/collections/user_activities')->
            args('streams', [
                'person_id' => $this->document['_id']
            ])->
            write();
    }
}
