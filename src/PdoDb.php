<?php

namespace killworm737\Pdo;

class PdoDb
{
    private $pdo;
    private $sQuery;
    private $settings;
    private $bConnected = false;
    private $log;
    private $parameters;
    private $connFile;
    public $sqlArr;
    public function __construct($connFile = '')
    {
        $this->ConnSetting($connFile);
        $this->log = new Log();
        $this->Connect();
        $this->parameters = array();
    }
    private function ConnSetting($connFile = '')
    {
        if ($connFile != '') {
            $tArr = yaml_parse_file($connFile);
            $this->settings = $tArr['default'];
        } else {
            $this->settings['host'] = getenv('DBHOST');
            $this->settings['username'] = getenv('DBUSER');
            $this->settings['password'] = getenv('DBPASS');
            $this->settings['database'] = getenv('DBNAME');
        }
        if (empty($this->settings['username'])) {
            echo '連線資訊異常，請檢查連線檔案或環境變數。';
            die();
        }
    }
    private function Connect()
    {
        $dsn = 'mysql:dbname='.$this->settings['database'].';host='.$this->settings['host'].'';
        try {
            $this->pdo = new PDO($dsn, $this->settings['username'], $this->settings['password'], array(
        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
      ));
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            $this->bConnected = true;
        } catch (PDOException $e) {
            echo $this->ExceptionLog($e->getMessage());
            die();
        }
    }
    public function CloseConnection()
    {
        $this->pdo = null;
    }
    private function Init($query, $parameters = '')
    {
        if (!$this->bConnected) {
            $this->Connect();
        }
        try {
            $this->sQuery = $this->pdo->prepare($query);
            $this->bindMore($parameters);
            if (!empty($this->parameters)) {
                foreach ($this->parameters as $param => $value) {
                    $type = PDO::PARAM_STR;
                    switch ($value[1]) {
          case is_int($value[1]):
            $type = PDO::PARAM_INT;
            break;
          case is_bool($value[1]):
            $type = PDO::PARAM_BOOL;
            break;
          case is_null($value[1]):
            $type = PDO::PARAM_NULL;
            break;
          }
                    $this->sQuery->bindValue($value[0], $value[1], $type);
                }
            }
            $this->sQuery->execute();
        } catch (PDOException $e) {
            echo $this->ExceptionLog($e->getMessage(), $query);
            die();
        }
        $this->parameters = array();
    }
    public function bind($para, $value)
    {
        $this->parameters[sizeof($this->parameters)] = [':'.$para, $value];
    }
    public function bindMore($parray)
    {
        if (empty($this->parameters) && is_array($parray)) {
            $columns = array_keys($parray);
            foreach ($columns as $i => &$column) {
                $this->bind($column, $parray[$column]);
            }
        }
    }
    public function query($query, $params = null, $fetchmode = PDO::FETCH_ASSOC)
    {
        $sqlArr = [];
        $query = trim(str_replace("\r", ' ', $query));
        $this->Init($query, $params);
        $rawStatement = explode(' ', preg_replace("/\s+|\t+|\n+/", ' ', $query));
        $statement = strtolower($rawStatement[0]);
        if ($statement === 'select' || $statement === 'show') {
            $sqlArr['data'] = $this->sQuery->fetchAll($fetchmode);
        } elseif ($statement === 'insert' || $statement === 'update' || $statement === 'delete') {
            $sqlArr['data'] = $this->sQuery->rowCount();
        }
        $this->pdo_sql_debug($query, $params);

        return $sqlArr['data'];
    }
    public function pdo_sql_debug($sql, $placeholders)
    {
        foreach ((array) $placeholders as $k => $v) {
            $sql = preg_replace('/:'.$k.'/', "'".$v."'", $sql);
        }
        $this->sqlArr[] = $sql;
    }
    public function lastInsertId()
    {
        return $this->pdo->lastInsertId();
    }
    public function beginTransaction()
    {
        return $this->pdo->beginTransaction();
    }
    public function executeTransaction()
    {
        return $this->pdo->commit();
    }
    public function rollBack()
    {
        return $this->pdo->rollBack();
    }
    public function column($query, $params = null)
    {
        $this->Init($query, $params);
        $Columns = $this->sQuery->fetchAll(PDO::FETCH_NUM);
        $column = null;
        foreach ($Columns as $cells) {
            $column[] = $cells[0];
        }

        return $column;
    }
    public function row($query, $params = null, $fetchmode = PDO::FETCH_ASSOC)
    {
        $this->Init($query, $params);
        $result = $this->sQuery->fetch($fetchmode);
        $this->sQuery->closeCursor();
        $this->pdo_sql_debug($query, $params);

        return $sqlArr;
    }
    public function single($query, $params = null)
    {
        $this->Init($query, $params);
        $result = $this->sQuery->fetchColumn();
        $this->sQuery->closeCursor();
        $this->pdo_sql_debug($query, $params);

        return $result;
    }
    private function ExceptionLog($message, $sql = '')
    {
        $exception = 'Unhandled Exception. <br />';
        $exception .= $message;
        $exception .= '<br /> You can find the error back in the log.';
        if (!empty($sql)) {
            $message .= "\r\nRaw SQL : ".$sql;
        }
        $this->log->write($message);

        return $exception;
    }
}
