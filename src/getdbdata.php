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
class getdbdata
{
    private $db_in;
    private $db_out;
    public $filter = [];
    public function __construct($inStr = 'twn', $outStr = 'loc')
    {
        $this->db_in = new PdoDb('includes/db_staging_'.$inStr.'.yml');
        $this->db_out = new PdoDb('includes/db_staging_'.$outStr.'.yml');
    }

    /**
     * @todo
     */
    public function test($sqls)
    {
        $this->db_out->beginTransaction();
        foreach ($sqls as $sql) {
            try {
                $this->db_out->qq($sql['sql'], $sql['data']);
            } catch (Exception $e) {
                echo $sql.'<br>';
                echo $e->getMessage().'<br>';
            }
        }
        $this->db_out->executeTransaction();
    }

    /**
     * [pushdata 執行execute].
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
        $tStr = str_replace("\n", ' ', $tStr);
        $tStr = str_replace('"', ' ', $tStr);
        $type = "'".mb_substr($tStr, 0, 40, 'UTF-8')."'";

        if ($n === null) {
            $type = 'NULL';
        }

        return $type;
    }

    /**
     * [getSqlSelect description].
     *
     * @param array $tables [description]
     *
     * @return [type] [description]
     *
     * @todo 用來查詢table資料，是想用來自動新增，但資料量太大時很快就當掉
     */
    public function getSqlSelect($tables = array())
    {
        $sql = [];
        foreach ($tables as $k => $v) {
            $tArr = array_keys($v);

            $tCols = implode('`,`', $tArr);

            $tStr = 'select `'.$tCols.'` from '.$k;

            $sql[$k]['sql'] = $tStr;
            $sql[$k]['data'] = $this->db_in->query($tStr);
        }

        return $sql;
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
        foreach ($sql as $k => $v) {
            if (!empty($v['data'])) {
                $tInsert[$k] = 'INSERT INTO `'.$k.'` (`'.$v['colstr'].'` ) VALUES ';

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
                $tInsert[$k] .= implode(',', $tArr).';';
            }
        }

        return $tInsert;
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
                $tStr = '`'.$vv['Field'].'` '.$vv['Type'];
                if ($vv['Collation'] != null) {
                    $tStr .= ' COLLATE '.$vv['Collation'].' ';
                }
                if ($vv['Null'] == 'NO') {
                    $tStr .= ' NOT NULL';
                }
                if ($vv['Default'] != null) {
                    if ($vv['Type'] == 'timestamp') {
                        $tStr .= ' DEFAULT '.$vv['Default'];
                    } else {
                        $tStr .= " DEFAULT '".$vv['Default']."' ";
                    }
                }
                if (!empty($vv['Extra'])) {
                    if ($vv['Type'] == 'timestamp') {
                        $tStr .= ' '.$vv['Extra'];
                    }
                }

                $tArr[] = $tStr;
            }

            $tDrop = 'DROP TABLE IF EXISTS `'.$k.'` ;';
            $tCols = implode(',', $tArr);
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
