<?php

class PanelRegistry
{
    private static $types = [];

    private static $instances = [];

    public static function types()
    {
        return array_keys(self::$types);
    }

    public static function driver($type, ManagePanel $manager)
    {
        $type = (string) $type;
        if (!isset(self::$types[$type])) {
            return null;
        }
        if (!isset(self::$instances[$type])) {
            $class = self::$types[$type] . 'PanelDriver';
            if (!class_exists($class, false)) {
                require_once __DIR__ . '/Drivers/' . self::$types[$type] . '.php';
            }
            self::$instances[$type] = new $class($manager);
        }
        return self::$instances[$type];
    }
}
