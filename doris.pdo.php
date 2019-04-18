<?php
final class Doris {
  private static $instances = array();
  private static $listdsn   = array();

  private $connection;
  private $type;
  private $dsn;
  private $protocol;
  private $authentication;
  private $hosts;
  private $database;
  private $parameters = [];

  private $log = false;
  private $flog  = 'doris.log';

  public static function showConnections() {
    return array(
      'dsn' => static::$listdsn,
      'cnx' => static::$instances,
    );
  }
  public static function registerDSN($cdr, $dsn) {
    $cdr = strtolower($cdr);
    if(!empty(static::$listdsn)) {
      foreach(static::$listdsn as $k => $d) {
        if($dsn == $d) {
          $dsn = '&' . $k;
          break;
        }
      }
    }
    static::$listdsn[$cdr] = $dsn;
  }
  public static function g($cdr = null) {
    return static::getInstance($cdr);
  }
  public static function getInstance($cdr = null) {
    if(!empty($cdr)) {
      $cdr = strtolower($cdr);
    }
    if(!is_null($cdr) && !array_key_exists($cdr, static::$instances)) {
      if(array_key_exists($cdr, static::$listdsn)) {
        if(strpos(static::$listdsn[$cdr], '&') === 0) {
          $cdr = trim(static::$listdsn[$cdr], '&');
          if(array_key_exists($cdr, static::$instances)) {
            return static::$instances[$cdr];
          } else {
            return static::$instances[$cdr] = new static(static::$listdsn[$cdr]);
          }
        } else {
          return static::$instances[$cdr] = new static(static::$listdsn[$cdr]);
        }
      } else {
        trigger_error('DSN no existe');
      }
    }
    if(is_null($cdr)) {
      if(empty(static::$instances)) {
        trigger_error('No se ha iniciado una Instancia');
      } else {
        return reset(static::$instances);
      }
    }
    return static::$instances[$cdr];
  }
  function __construct($dsn) {
    return $this->_init($dsn);
  }
  public function __clone() {
    trigger_error('La clonación de este objeto no está permitida', E_USER_ERROR);
  }
  static function init($cdr) {
    return Doris::getInstance($cdr);
  }
  /* Proceso de parseo de DSN */
  private function parseProtocol($dsn) {
    $regex = '/^(\w+):\/\//i';
    preg_match($regex, $dsn, $matches);
    if (isset($matches[1])) {
      $protocol = $matches[1];
      $this->protocol = $protocol;
    }
  }
  private function parseDsn($dsn) {
    $this->parseProtocol($dsn);
    if (null === $this->protocol) {
      return;
    }
    $dsn = str_replace($this->protocol.'://', '', $dsn);
    if (false === $pos = strrpos($dsn, '@')) {
      $this->authentication = ['username' => null, 'password' => null];
    } else {
      $temp = explode(':', str_replace('\@', '@', substr($dsn, 0, $pos)));
      $dsn = substr($dsn, $pos + 1);
      $auth = [];
      if (2 === count($temp)) {
        $auth['username'] = $temp[0];
        $auth['password'] = $temp[1];
      } else {
        $auth['username'] = $temp[0];
        $auth['password'] = null;
      }
      $this->authentication = $auth;
    }
    if (false !== strpos($dsn, '?')) {
      if (false === strpos($dsn, '/')) {
        $dsn = str_replace('?', '/?', $dsn);
      }
    }
    $temp = explode('/', $dsn);
    $this->parseHosts($temp[0]);
    if (isset($temp[1])) {
      $params = $temp[1];
      $temp = explode('?', $params);
      $this->database = empty($temp[0]) ? null : $temp[0];
      if (isset($temp[1])) {
        $this->parseParameters($temp[1]);
      }
    }
  }
  private function parseHosts($hostString) {
    preg_match_all('/(?P<host>[\w-._]+)(?::(?P<port>\d+))?/mi', $hostString, $matches);
    $hosts = null;
    foreach ($matches['host'] as $index => $match) {
      $port = !empty($matches['port'][$index]) ? (int) $matches['port'][$index] : null;
      $hosts = ['host' => $match, 'port' => $port];
    }
    $this->hosts = $hosts;
  }
  private function parseParameters($params) {
    $parameters = explode('&', $params);
    foreach ($parameters as $parameter) {
      $kv = explode('=', $parameter, 2);
      $this->parameters[$kv[0]] = isset($kv[1]) ? $kv[1] : null;
    }
  }
  function _init($dsn) {
    $this->parseDsn($dsn);
    $this->connect();
    if(!is_null($this->connection) && $this->type == 'pdo') {
      $this->exec("SET CHARACTER SET utf8");
    }
  }
  function debug($x = true) { 
    $this->log = $x;
  }
  function connect() {
    if ($this->protocol == 'mongodb') {
      $this->type = 'mongodb';
      $db = new MongoDB\Driver\Manager($this->protocol . '://' . $this->hosts['host'] . ':' . $this->hosts['port']);
      if (!$db) {
        $this->except("<b>Doris:</b> Can't connect to the database! ");
      }
    } else {
      $this->type = 'pdo';
      try {
        $dsn = $this->protocol . ':host=' . $this->hosts['host'] . ';dbname=' . $this->database;
        $db = new PDO($dsn, $this->authentication['username'], $this->authentication['password']);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      } catch (PDOException $e) {
        $this->except('<b>Doris:</b> Can\'t connect to database ' . $this->protocol . '!');
      }
    }
    $this->connection = $db;
  }
  function exec($query, $prepare = null, $is_cmd = false) {
    if ($this->type == 'pdo') {
      if($this->log) {
        _log($this->flog, $query, $prepare, $is_cmd);
      }
      try {
        $result = $this->connection->prepare($query);
        $rp = $result->execute($prepare);

      } catch(Exception $e) {
        echo "<pre>";
        print_r($e);
        echo $e->getMessage();
        exit;
        _log($this->flog, $e);
        return false;
      }
      if (!$rp) {
        $this->except('<b>Doris exec():</b>' . "\nQuery string is: " . $query);
      }
      return $result;

    } elseif ($this->type == 'mongodb') {
      if(!$is_cmd) {
        $query = is_array($query) ? $query : array($query);
        $query[1] = !empty($query[1]) ? $query[1] : array();
        $query[2] = !empty($query[2]) ? $query[2] : array();
        $_query = new MongoDB\Driver\Query($query[1], $query[2]);
        $result = $this->connection->executeQuery($query[0], $_query);
      } else {
        $command = new MongoDB\Driver\Command($query);
        $result = $this->connection->executeCommand($this->database, $command);
      }
      if (!$result) {
        $this->except('<b>Doris exec():</b>');
      }
    } else {
       $this->except('<b>Doris exec():</b> Database type "' . $this->type . '" unrecognized');
    }
    return $result;
  }
  function cmd($command, $first = false) {
    return $this->get($command, $first, $is_command = true);
  }
  function insert($table, $fields, $ignore = false) {
    if(empty($fields)) {
      return false;
    }
    $fieldlist = '';
    $valuelist = '';
    foreach ($fields as $field => $value) {
      $value = is_null($value) ? 'NULL' : "'" . $this->escape($value) . "'";
      $fieldlist .= ($fieldlist == '' ? '' : ', ') . $field;
      $valuelist .= ($valuelist == '' ? '' : ', ') . '?';
    }
    $sql = "INSERT " . ($ignore ? 'IGNORE ' : '') . "INTO " . $table . " (" . $fieldlist . ") VALUES (" . $valuelist .")";
    $this->exec($sql, array_values($fields));
    return $this->last_insert_id();
  }
  function insert_update($table, $fields) {
    if(empty($fields)) {
      return false;
    }
    $fieldlist  = '';
    $valuelist  = '';
    $updatelist = '';
    $tag = '*';
    $nfields = array();
    foreach ($fields as $field => $value) {
      $uniq = strpos($field, $tag) !== false;
      $field = str_replace('*', '', $field);
      #$value = is_null($value) ? 'NULL' : "'" . $this->escape($value) . "'";
      $fieldlist .= ($fieldlist == '' ? '' : ', ') . $field;
      $fieldl = str_replace('_', '', $field);
      $valuelist .= ($valuelist == '' ? '' : ', ') . ':' . $fieldl;
      if(!$uniq) {
        $updatelist .= ($updatelist == '' ? '' : ', ') . $field . ' = :' . $fieldl;
      }
      $nfields[$fieldl] = $value;
    }
    $sql = "INSERT INTO " . $table . " (" . $fieldlist . ") VALUES (" . $valuelist .") ON DUPLICATE KEY UPDATE " . $updatelist . ";";
    $this->exec($sql, $nfields);
    return $this->last_insert_id();
  }
  function delete($table, $where = null) {
    $sql = "DELETE FROM " . $table;
    if (empty($where)) {
      return false;
    }
    $sql .= " WHERE " . $where;
    $this->exec($sql);
    return true;
  }
  function deleteLogic($table, $where = null) {
    $sql = "UPDATE " . $table . ' SET eliminado = NOW()';
    if (empty($where)) {
      return false;
    }
    $sql .= " WHERE " . $where;
    $this->exec($sql);
    return true;
  }
  function update($table, $fields, $where = null) {
    $valuelist = $this->_sql_construct_set($fields);
    if(empty($valuelist)) {
      return false;
    }
    $valuelist = ' SET ' . $valuelist;
    $sql = "UPDATE " . $table . $valuelist;
    if (empty($where)) {
      return false;
    }
    $sql .= " WHERE " . $where;
    $this->exec($sql, array_values($fields));
    return true;
  }
  private function _sql_construct_set($fields) {
    $valuelist = '';
    if(!empty($fields)) {
      foreach ($fields as $field => $value) {
        if(is_null($value)) {
          $value = "NULL";
        } elseif(preg_match("/^(?<signo>[+-])(?<num>\d+)$/", $value, $m)) {
          if($m['signo'] == '+') {
            $value = $field . ' + ' . $m['num'];
          } elseif($m['signo'] == '-') {
            $value = $field . ' - ' . $m['num'];
          }
        } else {
          $value = "'" . $this->escape($value) . "'";
        }

        $valuelist .= ($valuelist == "" ? "" : ", ") . $field . ' = ?';// . $value;
      }
    }
    return $valuelist;
  }
  /*
    Vinculamos a Doris con la libreria Tablefy para el acceso de los datos como:
    paginacion, orden, limite, opciones
  */
#  function pagination($query, Pagination $pagination = null) {
  function pagination($query, &$pagination = null) {

    if(is_null($pagination)) {
      return $this->get($query);
    }
    if(!($pagination instanceof Pagination)) {
      $pagination = new Pagination('p5');
    }
    if(!$pagination->setRequest()) {
      return false;
    }
    if($this->type == 'pdo') {
      $query_encapsule = "SELECT count(*) FROM (" . $query . ")x";

    } elseif($this->type == 'mongodb') {
      $query = is_array($query) ? $query : array($query);
      $query[1] = !empty($query[1]) ? $query[1] : array();
      $query[2] = !empty($query[2]) ? $query[2] : array();
    } else {
      $this->except('type invalid');
    }
    $total = $this->count($query_encapsule);
    if(!$pagination->setNumResults($total)) {
      return false;
    }

    if($this->type == 'pdo') {
      $query = "SELECT * FROM (" . $query . ")x LIMIT " . $pagination->cantidad . " OFFSET " . $pagination->offset;

    } elseif($this->type == 'mongodb') {
      $query[2] = array_merge($query[2], array(
        'limit' => $pagination->cantidad,
        'skip'  => $pagination->offset,
      ));
    } else {
      $this->except('type invalid');
    }
    $rp = $this->get($query);
    if(!empty($rp)) {
      $_rp = array();
      foreach($rp as $k => $n) {
        $ind = $pagination->offset + $k;
        $_rp[$ind] = $n;
      }
      $rp = $_rp;
    }
    return $rp;
  }
  function query($sql) {
    return $sql;
  }
  function get($sql, $first = false, $is_command = false, $prepare = null) {
    $result = $this->exec ($sql, $prepare, $is_command);
    if($result === false) {
      return false;
    }
    if ($this->type == 'pdo') {
      if (count($result) == 0) {
        return false;

      } else {
        if ($first === false) {
          return $result->fetchAll(PDO::FETCH_ASSOC);
        }
        return $result->fetch(PDO::FETCH_ASSOC);
      }
    } elseif ($this->type == 'mongodb') {
      if(!empty($rp) || true) {
        $rp = $result->toArray();
        $rp = $this->std_to_array($rp);
      }
      return $rp;

    } else {
       $this->except('<b>Doris get():</b> No conozco el tipo de base de datos "' . $this->type . '"');
    }
  }
  function map($sql, $cb) {
    $rp = $this->get($sql);
    return !empty($rp) ? array_map($cb, $rp) : null;
  }
  private function std_to_array($d) {
    $d = is_object($d) ? get_object_vars($d) : $d;
    $ce =& $this;
    return is_array($d) ? array_map(function($n) use($ce) {
      return $ce->std_to_array($n);
    }, $d) : $d;
  }
  function count($query) {
    if($this->type == 'pdo') {
      $rp = $this->get($query, true);
      return !empty($rp) ? array_shift($rp) : 0;

    } elseif($this->type == 'mongodb') {
      $query = is_array($query) ? $query : array($query);
      $query[1] = !empty($query[1]) ? $query[1] : array();
      $query[2] = !empty($query[2]) ? $query[2] : array();
      $query[0] = str_replace($this->database . '.', '', $query[0]);
      $command = array('count' => $query[0], 'query' => $query[1]);
      $rp = $this->cmd($command);
      return current($rp)['n'];

    } else {
      $this->except('type invalid');
    }
  }
  static function escape($string) {
    return str_replace("'", "\\'", $string);
  }
  function last_insert_id() {
    if ($this->type == 'pdo') {
      return $this->connection->lastInsertId();

    } else {
      $this->except('<b>Doris:</b> I don\'t know how to return the last insert ID with this DB type!');
    }
  }
  function except($err = null) {
    echo $err;
    echo "<h1>Doris Error:</h1>\n<br><h3>Trace:</h3><br><pre>" . var_export(debug_backtrace(), true) . "</pre>";
    exit;
  }
  function time($t = null, $m = null) {
    if(!is_null($m))  {
      return date("Y-m-d H:i:s", strtotime($t . ' ' . $m . ':00'));
    }
    if(is_numeric($t)){
      return date("Y-m-d H:i:s", $t);
    } elseif(is_null($t)) {
      return date("Y-m-d H:i:s");
    } else {
      return date("Y-m-d H:i:s", strtotime($t));
    }
  }
  function transaction() {
    _log($this->flog, 'INI TRANSACTION =>');
    if($this->type == 'pdo') {
      return $this->connection->beginTransaction();
    } else {
      $this->except('error-type');
    }
  }
  function commit() {
    _log($this->flog, ' <= END TRANSACTION: COMMIT');
    if($this->type == 'pdo') {
      return $this->connection->commit();
    } else {
      $this->except('error-type');
    }
  }
  function rollback() {
    _log($this->flog, ' <= END TRANSACTION: ROLLBACK');
    if($this->type == 'pdo') {
      return $this->connection->rollback();
    }
  }
}

