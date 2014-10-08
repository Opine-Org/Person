<?php
namespace Opine\Person;

class Controller {
    private $separation;

    public function __construct ($separation) {
        $this->separation = $separation;
    }

    public function stream () {
        $this->separation->app('bundles/Person/app/collections/activity_stream')->
            layout('Person/collections/activity_stream')->
            template()->
            write();
    }
}