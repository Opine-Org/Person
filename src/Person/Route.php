<?php
namespace Opine\Person;

class Route {
    private $root;
    private $bundleRoot;
    private $route;
    private $separation;

    public function __construct ($container, $root, $bundleRoot) {
        $this->root = $root;
        $this->bundleRoot = $bundleRoot;
        $this->route = $container->route;
        $this->separation = $container->separation;
    }

    public function paths () {
    	$this->route->get('/Person/stream', function () {
    		$this->separation->app('bundles/Person/app/collections/activity_stream')->
    			layout('Person/collections/activity_stream')->
    			template()->
    			write();
    	});
    }
}