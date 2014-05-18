<?php
namespace Opine\Person;

class Route {
    private $root;
    private $bundleRoot;
    private $route;

    public function __construct ($container, $root, $bundleRoot) {
        $this->root = $root;
        $this->bundleRoot = $bundleRoot;
        $this->route = $container->route;
    }

    public function paths () {}
}