<?php
namespace WPHZ\UGC;

abstract class AbstractSingleton {
    private static array $instances = [];

    protected function __construct() {}
    private function __clone() {}

    public static function instance(): static {
        $class = static::class;
        if (!isset(self::$instances[$class])) {
            self::$instances[$class] = new static();
        }
        return self::$instances[$class];
    }
}
