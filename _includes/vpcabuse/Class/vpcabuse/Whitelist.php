<?php
declare(strict_types=1);

namespace vpcabuse;

use vpcabuse\Whitelistlog;
use vpcabuse\WhitelistMetadata;

class Whitelist extends \Database implements \vpcabuse\WhitelistInterface {
    
    protected $table = 'whitelist';
    protected $logtable = 'userlog';
    
    public function __construct(string $host, int $port, string $db, string $user, string $password) {
        $this->Load($host, $port, $db, $user, $password);
    }
    
    /* 
     *  Creates required tables
     */
    public function Create() : void {
        $q = "CREATE TABLE `{$this->table}` (
              `ctid` int(11) NOT NULL,
              `date` datetime DEFAULT current_timestamp(),
              `comment` text DEFAULT NULL,
              `expires` datetime DEFAULT NULL,
              PRIMARY KEY (`ctid`),
              UNIQUE KEY `ctid_UNIQUE` (`ctid`)
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='Stores whitelist CTIDs';";
        $this->Query($q);
        
        $q = "CREATE TABLE `{$this->logtable}` (
              `date` datetime NOT NULL DEFAULT current_timestamp(),
              `ctid` int(12) DEFAULT NULL,
              `action` varchar(20) DEFAULT NULL,
              `metadata` text DEFAULT NULL,
              KEY `ctid` (`ctid`),
              KEY `idx_userlog_date` (`date`)
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1;";
        $this->Query($q);
    }
    
    protected function Load(string $host, int $port, string $db, string $user, string $password) : void {
        $this->host 	= $host;
		$this->port 	= $port;
		$this->dbname 	= $db;
		$this->user 	= $user;
		$this->password = $password;
        $this->Connect();
    }
    
    public function Get(int $ctid = -1) : array {
        $q = "SELECT `ctid`, `date`, `comment`, `expires` FROM `{$this->table}`;";
        $args = array();
        
        if ($ctid > 0) {
            $q = "SELECT `ctid`, `date`, `comment`, `expires` FROM `{$this->table}`
                  WHERE `ctid`=:ctid;";
            $args = array(':ctid' => $ctid);
        }
        
		$result = $this->Query($q, $args);
        
        $newtable = array();
        
        for($i = 0, $size = sizeof($result); $i < $size; ++$i) {
            $newtable[$result[$i]['ctid']] = [
                'date_added' => $result[$i]['date'],
                'comment' => $result[$i]['comment'],
                'expires' => $result[$i]['expires']
            ];
        }

        return $newtable;
    }
    
    /* 
     *  Destroys required tables
     */
    public function Destroy() : void {
        $this->Query("DROP TABLE `{$this->table}`;");
        $this->Query("DROP TABLE `{$this->logtable}`;");
    }
    
    /*
     * @return int
     * 0 UPDATE
     * 1 NEW
     * -1 ERROR
     */
    public function Add(int $ctid, string $comment = '', WhitelistMetadata $metadata) : int {
        if ($this->Contains($ctid)) {
            $this->Update($ctid, $comment, $metadata);
            return 0;
        }
        
        $q = "INSERT INTO `{$this->table}` (`ctid`, `comment`, `expires`) VALUES (:ctid, :comment, :expires);";
		$result = $this->Query($q, array(
            ':ctid' => $ctid,
            ':comment' => $comment,
            ':expires' => $metadata->GetExpire(),
        ));
        
        if ($this->query->rowCount() != 1) 
            return -1;
        
        $q = "INSERT INTO `{$this->logtable}` (`ctid`, `action`, `metadata`) VALUES (:ctid, :action, :metadata);";
		$result = $this->Query($q, array(
            ':ctid' => $ctid,
            ':action' => Whitelistlog::ACTION_ADD,
            ':metadata' => $metadata->GetComment(),
        ));
        
        return 1;
    }
    
    protected function Update(int $ctid, string $comment = '', WhitelistMetadata $metadata) : void {
        $q = "UPDATE `{$this->table}` SET `date`=NOW(), `comment`=:comment, `expires`=:expires WHERE `ctid`=:ctid;";
        $result = $this->Query($q, array(
            ':ctid' => $ctid,
            ':comment' => $comment,
            ':expires' => $metadata->GetExpire(),
        ));
        
        if ($this->query->rowCount() != 1) 
            return;
        
        $q = "INSERT INTO `{$this->logtable}` (`ctid`, `action`, `metadata`) VALUES (:ctid, :action, :metadata);";
		$result = $this->Query($q, array(
            ':ctid' => $ctid,
            ':action' => Whitelistlog::ACTION_UPDATE,
            ':metadata' => $metadata->GetComment(),
        ));
    }
    
    public function Remove(int $ctid) : bool {
        $q = "DELETE FROM `{$this->table}` WHERE `ctid` = :ctid;";
		$this->Query($q, array(':ctid' => $ctid));
        
        if ($this->query->rowCount() != 1) 
            return false;
        
        $q = "INSERT INTO `{$this->logtable}` (`ctid`, `action`) VALUES (:ctid, :action);";
		$result = $this->Query($q, array(
            ':ctid' => $ctid,
            ':action' => Whitelistlog::ACTION_REMOVE
        ));

        return true;
    }
    
    public function Contains(int $ctid) : bool {
        $q = "SELECT `ctid` FROM `{$this->table}` WHERE `ctid` = :ctid LIMIT 1";
		$result = $this->Query($q, array(':ctid' => $ctid), \PDO::FETCH_COLUMN);
            
        return (sizeof($result) == 1 ? true : false);
    }
    
    public function Expired(int $ctid) : bool {
        $q = "SELECT `ctid` FROM `{$this->table}` WHERE `ctid` = :ctid AND `expires` < NOW() LIMIT 1;";
        $result = $this->Query($q, array(':ctid' => $ctid), \PDO::FETCH_COLUMN);
        
        return (sizeof($result) == 1 ? true : false);
    }
    
    protected function Expire(int $ctid) : bool {
        if (!$this->Expired($ctid))
            return false;
        
        $q = "DELETE FROM `{$this->table}` WHERE `ctid` = :ctid;";
		$this->Query($q, array(':ctid' => $ctid));
        
        if ($this->query->rowCount() != 1) 
            return false;
        
        $q = "INSERT INTO `{$this->logtable}` (`ctid`, `action`) VALUES (:ctid, :action);";
		$result = $this->Query($q, array(
            ':ctid' => $ctid,
            ':action' => Whitelistlog::ACTION_EXPIRE
        ));
        
        return true;
    }
    
    protected function ExpireOld() : void {
        $q = "SELECT `ctid` FROM `{$this->table}` WHERE `expires` < NOW();";
        $result = $this->Query($q, array(), \PDO::FETCH_COLUMN);
        
        foreach ($result as $ctid) {
            $this->Expire($ctid);
        }
    }
}

