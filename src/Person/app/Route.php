<?php
namespace Opine\Person;

class Route
{
    private $route;

    public function __construct($route)
    {
        $this->route = $route;
    }

    public function paths()
    {
        $this->route->get('/logout', 'personController@logout');
    }

    public static function location()
    {
        return __DIR__;
    }
}
