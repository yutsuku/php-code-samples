<?php
declare(strict_types=1);

abstract class BaseController {
    protected $request;
    
    public function __construct(Request $request) {
        $this->request = $request;
    }
    
    abstract public function Run();
}
