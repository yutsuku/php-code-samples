<?php defined('MW_PATH') || exit('No direct script access allowed');

abstract class PanoplyCommand extends CConsoleCommand
{
    public $username;
    public $password;
    public $charset = 'utf8';
    public $dsn;
    public $customer_id;
    
    public function actionIndex()
    {
        $logger = new PanoplyLogger($this);
        
        if (empty($this->username)) {
            $logger->logFail('Call this command with the --username=NAME param where NAME is your user name for selected database.');
            
            return 1;
        }
        if (empty($this->dsn)) {
            $logger->logFail('Call this command with the --dsn=STRING param where STRING is your fully qualified DSN.');
            
            return 1;
        }
        if (empty($this->customer_id)) {
            $logger->logFail('Call this command with the --customer_id=ID param where ID is customer ID.');
            
            return 1;
        }
        
        $logger->logStart();

        try {
            $connection = new PanoplyConnection($this->dsn, $this->username, $this->password, $this->charset);
            $results = $connection->query($this->getQuery())->fetchAll(PDO::FETCH_OBJ);
            $this->store($results);
        } catch (Exception $e) {
            $logger->logFail($e->getMessage());

            return 1;
        }

        $logger->logEnd();

        return 0;
    }

    abstract protected function getQuery();
    abstract protected function store($results);
}
