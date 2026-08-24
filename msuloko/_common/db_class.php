<?php
  class MysqlDb {
    var $db_link;
    var $msg;
    var $first;
    var $last;
    var $Page_cal;
    var $Next_Page_Main_Groupav;

    function err_print($msg) {
      syslog(LOG_DEBUG, "[DB ERROR] {$msg}");
      exit;
    }

    function __construct($host, $db_user, $db_Passwd) {
      if (!$this->db_link = mysqli_connect($host, $db_user, $db_Passwd)) {
        $msg = "DB 서버에 접속할 수 없습니다.";
        $this->err_print($msg);
      }
    }

    function SELECTDb($db_Writer) {
      if (!$chk = mysqli_SELECT_db($this->db_link , $db_Writer)) {
        $msg = "$db_Writer 데이터베이스에 접속할 수 없습니다.";
        $this->err_print($msg);
      }
      mysqli_set_charset($this->db_link, 'utf8mb4');
    }

    function query($Query) {
      if (!$Result = mysqli_query($this->db_link, $Query)) {
        $msg1 = "Query 수행을 할 수 없습니다.";
        $msg2 = "$Query";
        $msg = $msg1."<br>"."Query 내용 : ".mysqli_error()."=>".$Query;
        $this->err_print($msg);
      }
      return $Result;
    }

    // return insert id
    function query_id($Query) {
      if (!$Result = mysqli_query($this->db_link, $Query)) {
        $msg1 = "Query 수행을 할 수 없습니다.";
        $msg2 = "$Query";
        $msg = $msg1."<br>"."Query 내용 : ".mysqli_error()."=>".$Query;
        $this->err_print($msg);
      }
      $Result = mysqli_insert_id($this->db_link);
      return $Result;
    }

    function lock($table) {
      $this->query("LOCK TABLES $table WRITE");
    }

    function unlock() {
      $this->query("UNLOCK TABLES");
    }

    function get_data($Query) {
      $row = mysqli_fetch_array($this->query($Query), MYSQLI_ASSOC);
      /*
      while ( list($key, $val) = each($row)) {
        $row[$key] = stripslashes($val);
      }
      */
      return $row;
    }

    function get_data_one($Query) {
      $row = mysqli_fetch_array($this->query($Query));
      return $row[0];
    }

    function get_list($query) {
      $query_result = $this->query($query);
      $this->last  = @mysqli_num_rows($query_result) - 1;
      if ($this->last < "0") { return; }
      $i = 0;
      $tmp = array();
      while ($row = mysqli_fetch_array($query_result)) {
        if (isset($row)) {
          foreach ($row as $key => $val) {
            $tmp[$key][$i] = stripslashes($val);
          }
        }
        $i++;
      }
      return $tmp;
    }

    // 두개의 필드값만 처리할때 PRIMARY키가 키값으로 되는 특정 값을 구합
    function get_no_list($Query) {
      $query_result = $this->query($Query);
      $this->last = @mysqli_num_rows($query_result) - 1;
      if ($this->last < "0") { return; }
      $i = 0;
      while ($row = mysqli_fetch_array($query_result)) {
        $temp[$row[0]] = $row[1];
        $i++;
      }
      return $temp;
    }
  }
?>
