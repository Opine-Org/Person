<?php
namespace Opine\Person;

class Route {
    private $root;
    private $route;
    private $separation;

    public function __construct ($root, $route, $separation) {
        $this->root = $root;
        $this->route = $route;
        $this->separation = $separation;
    }

    public function paths ($bundleRoot='') {
    	/*
        $this->route->get('/Person/stream', function () {
    		$this->separation->app('bundles/Person/app/collections/activity_stream')->
    			layout('Person/collections/activity_stream')->
    			template()->
    			write();
    	});
        */
    }

    public function location () {
        return __DIR__;
    }
}