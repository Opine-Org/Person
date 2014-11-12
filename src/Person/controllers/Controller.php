<?php
namespace Opine\Person;

class Controller {
    private $layout;

    public function __construct ($layout) {
        $this->layout = $layout;
    }

    public function stream () {
        $this->layout->app('Person/collections/activity_stream')->
            layout('Person/collections/activity_stream')->
            write();
    }
}