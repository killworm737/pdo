<?php

namespace killworm737\Pdo;

/**
 * 取得資料庫中所有表格資料，可以建立 create table、select、insert.
 *
 * @param array $filter['table']     要排除的table
 * @param array $filter['ex_table']  只有選定的才不排除的table
 * @param array $filter['col_type']  要排除的欄位格式
 * @param array $filter['col_field'] 要排除的欄位名稱
 * @param array $filter['sqlstr']    自訂sql
 * @param int   $filter['maxrow']    ，限制最大筆數
 * @param int   $filter['minrow']    ，限制最小筆數
 *
 * @todo 若使用bind會無法控制，還在找尋原因，可能是pdo寫法有問題
 */
class GetDbData
{
    private $db_in;
    private $db_out;
    public $filter = [];

    /**
     * @param $inStr['env'] 來源環境
     * @param $inStr['area'] 來源區域
     * @param $outStr['env'] 匯入環境
     * @param $outStr['area'] 匯入區域
     */
    public function __construct($inStr = array('env'=>'staging','area'=>'twn'), $outStr = array('env'=>'local','area'=>'new'))
    {
        $this->db_in = new PdoDb('includes/environment/'.$inStr['env'].'/db_'.$inStr['area'].'.yml');
        $this->db_out = new PdoDb('includes/environment/'.$outStr['env'].'/db_'.$outStr['area'].'.yml');
    }

    /**
     * @todo
     */
    public function test($sqls)
    {
        $tTables = $this->db_in->query($sqls);
        return $tTables;
    }

    /**
     * [pushdata 執行SQL execute].
     *
     * @param [type] $sqls [description]
     *
     * @return [type] [description]
     */
    public function pushdata($sqls)
    {
        $this->db_out->beginTransaction();
        foreach ($sqls as $sql) {
            try {
                $this->db_out->query($sql);
            } catch (Exception $e) {
                echo $sql.'<br>';
                echo $e->getMessage().'<br>';
            }
        }
        $this->db_out->executeTransaction();
    }

    /**
     * [pushdata2 description].
     *
     * @todo 測試用function
     */
    public function pushdata2($sqls)
    {
        $this->db_out->beginTransaction();
        foreach ($sqls['data'] as $data) {
            try {
                $tt['id'] = $data['id'];
                $tt['name'] = $data['name'];
                $tt['create_user_id'] = $data['create_user_id'];
                $tt['create_user_name'] = $data['create_user_name'];
                $tt['create_time'] = $data['create_time'];
                $tt['modify_user_id'] = $data['modify_user_id'];
                $tt['modify_user_name'] = $data['modify_user_name'];
                $tt['modify_time'] = $data['modify_time'];
                $tt['deleted'] = $data['deleted'];

                $qq = $this->db_out->query($sqls['sql'], $tt);
            } catch (Exception $e) {
                echo $e.'<br>';
                echo $e->getMessage().'<br>';
            }
        }
        $this->db_out->executeTransaction();
    }

    /**
     * [strsetup 以array_map將array的值都加上 :xxx].
     *
     * @param [type] $n [description]
     *
     * @return [type] [description]
     */
    public function strsetup($n)
    {
        $tStr = $n;
        $tStr = str_replace(array("\r", "\n", "\r\n", "\n\r"), ' ', $tStr);
        $tStr = str_replace('"', ' ', $tStr);
        $tStr = str_replace("'", ' ', $tStr);
        $tStr = str_replace(";", ' ', $tStr);
        $type = "'".mb_substr($tStr, 0, 40, 'UTF-8')."'";

        if ($n === null) {
            $type = 'NULL';
        }

        return $type;
    }

    /**
     * [querySql description].
     *
     * @param array $tables [description]
     *
     * @return [type] [description]
     *
     * @todo 用來查詢table資料，是想用來自動新增，但資料量太大時很快就當掉
     */
    public function querySql($sqlstrs)
    {
        // dump($this->db_out);
        foreach ($sqlstrs as $sqlstr) {
            // $this->db_out->query($sqlstr);
            $this->db_in->query($sqlstr);
        }
    }

    public function save_txt($params, $filename = 'save_yml.txt')
    {
        $fp = fopen($_SERVER['DOCUMENT_ROOT'].'/cache/aa'.$filename, 'w+');
        foreach ($params as $k => $v) {
            fwrite($fp, $v."\r\n");
        }

        fclose($fp);
    }

    /**
     * [getSqlInsert description].
     *
     * @param array $tables [description]
     *
     * @return [type] [description]
     */
    public function getSqlInsert($tables = array())
    {
        foreach ($tables as $k => $v) {
            $tArr = array_keys($v);

            $tCols = implode('`,`', $tArr);
            if ($this->filter['sqlstr'] != '') {
                $tStr = str_replace(':sqlstr', $k, $this->filter['sqlstr']);
            } else {
                $tStr = 'select `'.$tCols.'` from '.$k.' ';
            }

            $sql[$k]['cols'] = $tArr;
            $sql[$k]['colstr'] = $tCols;

            $sql[$k]['data'] = $this->db_in->query($tStr);
        }




        $tInsert = [];
        $tttt = [];
        foreach ($sql as $k => $v) {

            if (!empty($v['data'])) {
                $tttt[$k]['col'] = '`'.$v['colstr'].'`';

                $tArr = [];
                $tStr = '';
                $i = 0;
                foreach ($v['data'] as $row) {
                    $row = array_map(array($this, 'strsetup'), $row);
                    $tStr = '('.implode(',', $row).')';
                    if (substr_compare($tStr, '(null', 0, 5, true) != 0) {
                        $tArr[] = $tStr;
                    }
                }
                $tttt[$k]['val'] = $tArr;
            }
        }
        return $tttt;
    }
    /**
     * [getSqlDrop 依照getTables結果，建立 drop 語法].
     *
     * @param array $tables [description]
     *
     * @return array [description]
     */
    public function getSqlDrop($tables = array())
    {
        foreach ($tables as $k => $v) {
            $tDrop = 'DROP TABLE `'.$k.'` ;';
            $sql[] = $tDrop;
        }

        return $sql;
    }


    public function array_column($input = null, $columnKey = null, $indexKey = null)
    {
        // Using func_get_args() in order to check for proper number of
        // parameters and trigger errors exactly as the built-in array_column()
        // does in PHP 5.5.
        $argc = func_num_args();
        $params = func_get_args();
        if ($argc < 2) {
            trigger_error("array_column() expects at least 2 parameters, {$argc} given", E_USER_WARNING);
            return null;
        }
        if (!is_array($params[0])) {
            trigger_error(
                'array_column() expects parameter 1 to be array, ' . gettype($params[0]) . ' given',
                E_USER_WARNING
            );
            return null;
        }
        if (!is_int($params[1])
            && !is_float($params[1])
            && !is_string($params[1])
            && $params[1] !== null
            && !(is_object($params[1]) && method_exists($params[1], '__toString'))
        ) {
            trigger_error('array_column(): The column key should be either a string or an integer', E_USER_WARNING);
            return false;
        }
        if (isset($params[2])
            && !is_int($params[2])
            && !is_float($params[2])
            && !is_string($params[2])
            && !(is_object($params[2]) && method_exists($params[2], '__toString'))
        ) {
            trigger_error('array_column(): The index key should be either a string or an integer', E_USER_WARNING);
            return false;
        }
        $paramsInput = $params[0];
        $paramsColumnKey = ($params[1] !== null) ? (string) $params[1] : null;
        $paramsIndexKey = null;
        if (isset($params[2])) {
            if (is_float($params[2]) || is_int($params[2])) {
                $paramsIndexKey = (int) $params[2];
            } else {
                $paramsIndexKey = (string) $params[2];
            }
        }
        $resultArray = array();
        foreach ($paramsInput as $row) {
            $key = $value = null;
            $keySet = $valueSet = false;
            if ($paramsIndexKey !== null && array_key_exists($paramsIndexKey, $row)) {
                $keySet = true;
                $key = (string) $row[$paramsIndexKey];
            }
            if ($paramsColumnKey === null) {
                $valueSet = true;
                $value = $row;
            } elseif (is_array($row) && array_key_exists($paramsColumnKey, $row)) {
                $valueSet = true;
                $value = $row[$paramsColumnKey];
            }
            if ($valueSet) {
                if ($keySet) {
                    $resultArray[$key] = $value;
                } else {
                    $resultArray[] = $value;
                }
            }
        }
        return $resultArray;
    }
    /**
     * [getSqlCreate 依照getTables結果，建立create語法].
     *
     * @param array $tables [description]
     *
     * @return array [description]
     */
    public function getSqlCreate($tables = array())
    {
        foreach ($tables as $k => $v) {
            $tArr = [];
            foreach ($v as $kk => $vv) {
            $ttt[$k][$vv['Key']][] = $vv['Field'];

                $tStr = '`'.$vv['Field'].'` '.$vv['Type'];
                if ($vv['Collation'] != null) {
                    $tStr .= ' COLLATE '.$vv['Collation'].' ';
                }
                if ($vv['Null'] == 'NO') {
                    $tStr .= ' NOT NULL';
                }
                if ($vv['Default'] !== null) {
                    if (in_array($vv['Default'], ['timestamp','CURRENT_TIMESTAMP'])) {
                        $tStr .= ' DEFAULT '.$vv['Default'];
                    } else {
                        $tStr .= " DEFAULT '".$vv['Default']."' ";
                    }
                }
                if (!empty($vv['Extra'])) {
                    $tStr .= ' '.$vv['Extra'] . ' ';
                }

                $tArr[] = $tStr;
            }
            $pri = [];
            $mul = [];
            $uni = [];
            foreach ($v as $kk => $vv) {
                if ($vv['Key'] == 'PRI') {
                    $pri[] = '`'.$vv['Field'].'`';
                }
                if ($vv['Key'] == 'MUL') {
                    $mul[] = '`'.$vv['Field'].'`';
                }
                if ($vv['Key'] == 'UNI') {
                    $uni[] = '`'.$vv['Field'].'`';
                }
            }
            if (count($pri)>0) {
                $tArr[] = ' PRIMARY KEY (' .implode(',', $pri).  ')  ';
            }
            if (count($mul)>0) {
                foreach ($mul as $value) {
                    $tArr[] = " KEY {$value} ({$value})  ";
                }
            }
            if (count($uni)>0) {
                foreach ($uni as $value) {
                    $tArr[] = " UNIQUE {$value} ({$value})  ";
                }
            }

            $tDrop = 'DROP TABLE IF EXISTS `'.$k.'` ;';
            $tCols = implode(chr(13) . ',' , $tArr);
            $tCreate = 'CREATE TABLE `'.$k.'` (
                '.$tCols.'
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci ;
            ';


            $sql[] = $tDrop;
            $sql[] = $tCreate;
        }
        return $sql;
    }

    /**
     * [getSqlDelete 依照getTables結果，建立 delete 語法].
     *
     * @param array $tables [description]
     *
     * @return array [description]
     */
    public function getSqlDelete($tables = array())
    {
        foreach ($tables as $k => $v) {
            $tDrop = 'DELETE FROM  `'.$k.'` ;';
            $sql[] = $tDrop;
        }

        return $sql;
    }

    /**
     * [getSqlTRUNCATE 依照getTables結果，建立 TRUNCATE 語法].
     *
     * @param array $tables [description]
     *
     * @return array [description]
     */
    public function getSqlTRUNCATE($tables = array())
    {
        foreach ($tables as $k => $v) {
            $tDrop = 'TRUNCATE TABLE `'.$k.'` ;';
            $sql[] = $tDrop;
        }

        return $sql;
    }

    /**
     * [getTableList 取得table與field清單].
     * 利用 SHOW TABLE STATUS 取得table清單.
     *
     * @return array 回傳table欄位清單
     */
    public function getTableList()
    {
        $filter_table = (array) $this->filter['table'];
        $filter_ex_table = (array) $this->filter['ex_table'];
        $filter_col_type = (array) $this->filter['col_type'];
        $filter_col_field = (array) $this->filter['col_field'];
        $filter_minrow = $this->filter['minrow'];
        $filter_maxrow = $this->filter['maxrow'];

        $tTables = $this->db_in->query('SHOW TABLE STATUS');
        $tables = [];
        $tArr = [];
        $i = 0;
        foreach ($tTables as $k => $v) {
            if ($v['Rows'] === null) {
                continue;
            };

            if ($v['Rows'] < $filter_minrow and !empty($filter_minrow)) {
                continue;
            };
            if ($v['Rows'] > $filter_maxrow and !empty($filter_maxrow)) {
                continue;
            };
            if (in_array($v['Name'], $filter_table)) {
                continue;
            }
            if (!empty($filter_ex_table)) {
                if (!in_array($v['Name'], $filter_ex_table)) {
                    continue;
                }
            }

            $tables[$v['Name']] = [];
        }
        foreach ($tables as $k => $v) {
            $table = $k;
            $columns = $this->db_in->query('SHOW FULL COLUMNS FROM '.$table);
            $tCols = [];

            foreach ($columns as $index => $column) {
                if (in_array($column['Type'], $filter_col_type)) {
                    continue;
                }
                if (in_array($column['Name'], $filter_col_field)) {
                    continue;
                }

                $tCols[$column['Field']] = $column;
            }
            $tArr[$table] = $tCols;
        }
        return $tArr;
    }
}
