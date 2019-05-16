<?php

/**
  * This file contains logic for temporary bans for reverse proxy
  */

if (!defined('APPPATH')) 
    die('Unknown Application path');

require_once(APPPATH . DIRECTORY_SEPARATOR . "vendor" . DIRECTORY_SEPARATOR . "rate_limiter.php");

class Throttle
{
    private $request;
    private $clientIP;
    private $resources = array();
    private static $resourcesResponse = array(); // for debugging
    
    const MESSAGE_BANNED = "Your IP has been restricted because of too many attempts. Please try again later.\n";
    const MESSAGE_FORMAT = <<<EOD
%s<br><br>

Include the following message when contacting support if you do believe this is a mistake:<br>

[%s/%s]
EOD;
    
    public function __construct()
    {
        $this->detectClientIP();
        $this->setResources();
    }
    
    protected function setResources()
    {
        // format: $resource, $limit_group, $rate_per_period, $period_in_seconds
        array_push($this->resources,
            // no anonymous function here, WordPress needs "special" attention
            array('wordpress_login_page', $this->getClientIP(), 5, 10800),
            
            // index.php, 20 hits in 60 seconds
            array(
                function($limit_group, $rate_per_period, $period_in_seconds, $add_to_rate, &$requestInit, $request) {
                    $resource = 'index.php';
                    
                    // check if user is banned due to this
                    if ($add_to_rate <= 0) {
                        if (!check_within_rate_limit($resource, $limit_group, $rate_per_period, $period_in_seconds, 0)) {
                            die(sprintf(self::MESSAGE_FORMAT,
                                self::MESSAGE_BANNED,
                                hash('sha256', $resource),
                                hash('sha256', $limit_group)
                            ));
                        }
                        Throttle::setResourceResponse($resource, 1);
                        return;
                    }
                    
                    // handle only vaild requests
                    if (strpos($request->uri, 'index.php') === false && $request->uri !== '/') {
                        Throttle::setResourceResponse($resource, 2);
                        return;
                    }
                    
                    if (!$limit_group) {
                        Throttle::setResourceResponse($resource, 3);
                        return;
                    }
                    
                    Throttle::setResourceResponse($resource, 0);
                    if (!check_within_rate_limit('index.php', $limit_group, $rate_per_period, $period_in_seconds, $add_to_rate)) {
                        die(sprintf(self::MESSAGE_FORMAT, 
                            self::MESSAGE_BANNED, 
                            hash('sha256', $resource),
                            hash('sha256', $limit_group)
                        ));
                    }
                    
                    return;
                },
                $this->getClientIP(), 20, 60
            ),
            
            // xmlrpc.php, 10 hits in 60 minutes
            array(
                function($limit_group, $rate_per_period, $period_in_seconds, $add_to_rate, &$requestInit, $request) {
                    $resource = 'xmlrpc.php';
                    
                    // check if user is banned due to this
                    if ($add_to_rate <= 0) {
                        if (!check_within_rate_limit($resource, $limit_group, $rate_per_period, $period_in_seconds, 0)) {
                            die(sprintf(self::MESSAGE_FORMAT,
                                self::MESSAGE_BANNED,
                                hash('sha256', $resource),
                                hash('sha256', $limit_group)
                            ));
                        }
                        Throttle::setResourceResponse($resource, 1);
                        return;
                    }
                    
                    // handle only vaild requests
                    if (strpos($request->uri, 'xmlrpc.php') === false) {
                        Throttle::setResourceResponse($resource, 2);
                        return;
                    }
                    
                    if (!$limit_group) {
                        Throttle::setResourceResponse($resource, 3);
                        return;
                    }
                    
                    Throttle::setResourceResponse($resource, 0);
                    if (!check_within_rate_limit('xmlrpc.php', $limit_group, $rate_per_period, $period_in_seconds, $add_to_rate)) {
                        die(sprintf(self::MESSAGE_FORMAT,
                            self::MESSAGE_BANNED,
                            hash('sha256', $resource),
                            hash('sha256', $limit_group)
                        ));
                    }

                    return;
                },
                $this->getClientIP(), 10, 3600
            )
        );
    }

    /**
    * To be used by anything external, place new rules in setResources if possible
    * @param array $resource(string|callable $resource, string $limit_group, int $rate_per_period, int $period_in_seconds)
    * @return int
    */
    public function addResource($resource)
    {
        return array_push($this->resources, (array)$resource);
    }
    
    /**
    * Calls all defined resources
    * @param int $add_to_rate = 0, stdClass|null $requestInit = null
    */
    public function checkLimit($add_to_rate = 0, &$requestInit = null)
    {
        // check any resources that can be called
        // and call them passing 
        // $limit_group, $rate_per_period, $period_in_seconds, $add_to_rate, &$requestInit, $request
        // if $add_to_rate is 0, then $requestInit will always be null
        //
        // see proxy.php @ pullBackend method for more details on $requestInit
        for($i = 0, $size = sizeof($this->resources); $i < $size; ++$i) {
            if (is_callable( $this->resources[$i][0] )) {
                // any function
                $this->resources[$i][0](
                    $this->resources[$i][1],
                    $this->resources[$i][2],
                    $this->resources[$i][3],
                    $add_to_rate,
                    $requestInit,
                    $this->getRequest()
                );
            } elseif (is_callable( array($this, $this->resources[$i][0]) )) {
                // any method inside this class
                $this->{$this->resources[$i][0]}(
                    $this->resources[$i][1],
                    $this->resources[$i][2],
                    $this->resources[$i][3],
                    $add_to_rate,
                    $requestInit,
                    $this->getRequest()
                );
            }
        }
        $this->setResourceHeader();
    }
    
    /**
     * @return string|null
     */
    protected function detectClientIP()
    {
        // detect remote client IP
        if (isset($_SERVER['HTTP_X_REAL_IP']) && isset($_SERVER['SERVER_ADDR'])) {
            $a = filter_var($_SERVER['HTTP_X_REAL_IP'], FILTER_VALIDATE_IP);
            $b = filter_var($_SERVER['SERVER_ADDR'], FILTER_VALIDATE_IP);
            
            if ($a && $b && $a !== $b) {
                $this->clientIP = $a;
            }
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && isset($_SERVER['SERVER_ADDR'])) {
            $a = filter_var($_SERVER['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP);
            $b = filter_var($_SERVER['SERVER_ADDR'], FILTER_VALIDATE_IP);
            
            if ($a && $b && $a !== $b) {
                $this->clientIP = $a;
            }
        } elseif (isset($_SERVER['REMOTE_ADDR']) && isset($_SERVER['SERVER_ADDR'])) {
            $a = filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP);
            $b = filter_var($_SERVER['SERVER_ADDR'], FILTER_VALIDATE_IP);
            
            if ($a && $b && $a !== $b) {
                $this->clientIP = $a;
            }
        }
        
        return $this->clientIP;
    }
    
    /**
     * @return string|null
     */
    public function getClientIP()
    {
        return $this->clientIP;
    }
    
    /**
     * To be used internally by resources so we can debug easily
     * It is used to generate debug HTTP header CH-Throttle
     * Suggested $status value are 
     *  0 for normal run
     * -1 for unexpected behaviour
     *  1 or higher for expected but not normal behaviour
     * @param string $resouce, int $status
     */
    public static function setResourceResponse($resource, $status)
    {
        for($i = 0, $size = sizeof(self::$resourcesResponse); $i < $size; ++$i) {
            if (self::$resourcesResponse[$i][0] === $resource) {
                self::$resourcesResponse[$i] = array($resource, $status);
                return;
            }
        }
        
        self::$resourcesResponse[] = array($resource, $status);
    }
    
    /**
     * For debugging purposes only
     *
     * Change CHDEBUG to true in ../index.php to enable headers
     * for every request
     */
    public function setResourceHeader()
    {
        if (!CHDEBUG) {
            return;
        }
        
        $header = 'CH-Throttle: ';
        $output = '';
        $size = sizeof(self::$resourcesResponse);
        
        if ($size == 0) {
            return;
        }
        
        for($i = 0; $i < $size; ++$i) {
            if ($i == $size) {
                $output .= urlencode((string)self::$resourcesResponse[$i][0]);
                $output .= '=';
                $output .= urlencode((string)self::$resourcesResponse[$i][1]);
            } else {
                $output .= urlencode((string)self::$resourcesResponse[$i][0]);
                $output .= '=';
                $output .= urlencode((string)self::$resourcesResponse[$i][1]);
                $output .= '; ';
            }
        }
        
        header($header.$output);
    }
    
    /**
     * @return stdClass $request
     */
    public function getRequest()
    {
        if ($this->request)
            return $this->request;
        
        $requestURI = (object)parse_url($_SERVER['REQUEST_URI']);

        $request = new stdClass();
        $request->url = $requestURI->path;
        $request->pathInfo = (object)pathinfo($requestURI->path);
        $request->headers = (function_exists('getallheaders') ? getallheaders() : '');

        if (!empty($requestURI->query)) {
            parse_str($requestURI->query, $request->query);
        }

        if (!empty($request->query)) {
            if (isset($request->query["CHDEBUG"])) {
                $this->fastDebug = true;
                $this->errors["MD5"] = "MD5: " . md5($_SERVER["HTTP_HOST"]);
                unset($request->query["CHDEBUG"]);
            }
        }

        $this->request = $request;
        $this->request->uri = $_SERVER["REQUEST_URI"];
        
        return $this->request;
    }
    
     /**
     * Prevents from brute-forcing the WP login page
     * @param string $limit_group, int $rate_per_period, int $period_in_seconds, int $add_to_rate, stdClass &$requestInit, stdClass $request
     * @return void
     */
    protected function wordpress_login_page($limit_group, $rate_per_period, $period_in_seconds, $add_to_rate, &$requestInit, $request)
    {
        $resource = 'wordpress_login_page';
        
        if (!$limit_group) {
            Throttle::setResourceResponse($resource, 1);
            return;
        }
        
        if ($add_to_rate <= 0) {
            if (!check_within_rate_limit($resource, $limit_group, $rate_per_period, $period_in_seconds, 0)) {
                die(sprintf(self::MESSAGE_FORMAT,
                    self::MESSAGE_BANNED,
                    hash('sha256', $resource),
                    hash('sha256', $limit_group)
                ));
            }
            Throttle::setResourceResponse($resource, 2);
            return;
        }
        
        // handle only vaild requests
        if (strpos($request->uri, 'wp-login.php') === false) {
            Throttle::setResourceResponse($resource, 3);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            Throttle::setResourceResponse($resource, 4);
            return;
        }
        
        if (empty($requestInit->headers["Set-Cookie"])) {
            Throttle::setResourceResponse($resource, 5);
            // WP always returns wordpress_test_cookie
            return;
        }

        if (is_array($requestInit->headers['Set-Cookie']))
        {
            Throttle::setResourceResponse($resource, 6);
            // vaild login
            return;
        }
        
        if (is_string($requestInit->headers["Set-Cookie"]) && strpos($requestInit->headers["Set-Cookie"], 'wordpress_test_cookie') !== false) {
            Throttle::setResourceResponse($resource, 0);
            // failed login attempt into wordpress
            if (!check_within_rate_limit($resource, $limit_group, $rate_per_period, $period_in_seconds, $add_to_rate)) {
                die(sprintf(self::MESSAGE_FORMAT,
                    self::MESSAGE_BANNED,
                    hash('sha256', $resource),
                    hash('sha256', $limit_group)
                ));
            }
            return;
        }
        
        Throttle::setResourceResponse($resource, -1);
    }
}
