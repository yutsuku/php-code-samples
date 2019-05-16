<?php
declare(strict_types=1);

class Router {
    protected static $config;
    private function __construct() {}
    
    public static function SetConfig(RouterConfig $config) : void {
        self::$config = $config;
    }
    
    public static function Route(Request $request) : object {
        $controllerClass = self::getControllerClass($request);
        
        return new $controllerClass($request);
    }
    
    public static function GetControllerClass(Request $request) : string {
        self::validateConfig();

        $className = self::$config::GetClasss($request);
        
        if (!$className || !class_exists($className))
            throw new Exception('Class: "' . $className . '" not found', 1);

        return $className;
    }
    
    private static function validateConfig() : void {
        if (self::$config instanceof RouterConfig)
            return;
        
        throw new Exception(
            'Router::config has not bet set! You must call Router::SetConfig() before calling this method.'
        , 2);
    }           
}
