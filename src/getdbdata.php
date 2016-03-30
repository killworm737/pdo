<?php

namespace killworm737\Pdo;

use killworm737\Pdo\PdoDb;
/**
 * 取得資料庫中所有表格資料，可以建立 create table、select、insert
 * @param array $filter['table'] 要排除的table
 * @param array $filter['ex_table'] 只有選定的才不排除的table
 * @param array $filter['col_type'] 要排除的欄位格式
 * @param array $filter['col_field'] 要排除的欄位名稱
 * @param int $filter['maxrow'] ，限制最大筆數
 * @param int $filter['minrow'] ，限制最小筆數
 * @todo 若使用bind會無法控制，還在找尋原因，可能是pdo寫法有問題
 */
class GetDbData
{
    private $db_in;
    private $db_out;
    public $filter = [];
    public function __construct($inStr = 'twn',$outStr = 'loc')
    {
        $this->db_in = new PdoDb('includes/db_staging_'.$inStr.'.yml');
        $this->db_out = new PdoDb('includes/db_staging_'.$outStr.'.yml');
    }

    /**
     * [pushdata 執行execute]
     * @param  [type] $sqls [description]
     * @return [type]       [description]
     */
    function pushdata($sqls)
    {

        $this->db_out->beginTransaction();
        foreach ($sqls as $sql) {
            try {
                $this->db_out->query($sql);
            } catch (Exception $e) {
                echo $sql. '<br>';
                print $e->getMessage() . '<br>';
            }
        }
        $this->db_out->executeTransaction();
    }

    /**
     * [pushdata2 description]
     * @todo 測試用function
     */
    function pushdata2($sqls)
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

                $qq = $this->db_out->query($sqls['sql'],$tt);
            } catch (Exception $e) {
                echo $e. '<br>';
                print $e->getMessage() . '<br>';
            }
        }
        $this->db_out->executeTransaction();


    }

    /**
     * [strsetup 以array_map將array的值都加上 :xxx]
     * @param  [type] $n [description]
     * @return [type]    [description]
     */
    function strsetup($n)
    {
        $n = ':' . $n;
        // $n = '?';
        return($n);
    }

    /**
     * [getSqlSelect description]
     * @param  array  $tables [description]
     * @return [type]         [description]
     * @todo 用來查詢table資料，是想用來自動新增，但資料量太大時很快就當掉
     */
    function getSqlSelect($tables = array())
    {
        $sql = [];
        foreach ($tables as $k => $v) {
            $tArr = array_keys($v);

            $tCols = implode('`,`', $tArr);

            $tStr = 'select `'.$tCols.'` from '.$k ;

            $sql[$k]['sql'] = $tStr;
            $sql[$k]['data'] = $this->db_in->query($tStr);

        }

        return $sql;
    }

    /**
     * [getSqlInsert description]
     * @param  array  $tables [description]
     * @return [type]         [description]
     */
    function getSqlInsert($tables = array())
    {
        foreach ($tables as $k => $v) {
            $tArr = array_keys($v);

            $tCols = implode('`,`', $tArr);

            $tStr = 'select `'.$tCols.'` from '.$k . ' limit 1000';

            $sql[$k]['cols'] = $tArr;
            $sql[$k]['colstr'] = $tCols;
            $sql[$k]['data'] = $this->db_in->query($tStr);

        }

        $tInsert = [];
        foreach ($sql as $k => $v) {
            $tInsert[$k]['sql'] = 'INSERT INTO `'.$k . '` (`'.$v['colstr'].'` ) VALUES ';
            $tInsert[$k]['data'] = $v['data'];
            $tArr = [];

            $v['cols'] = array_map(array( $this, 'strsetup' ), $v['cols']);

            $tInsert[$k]['sql'] .= '(' . implode(",", $v['cols']) . ')';
        }
        return $tInsert;
    }

    /**
     * [getSqlCreate 依照getTables結果，建立create語法].
     * @param array $tables [description]
     * @return array [description]
     */
    function getSqlCreate($tables = array())
    {
        foreach ($tables as $k => $v) {
            $tArr = [];
            foreach ($v as $kk => $vv) {
                $tStr = '`' . $vv['Field'] . '` ' . $vv['Type']  ;
                if ($vv['Collation'] != NULL ) $tStr .= " COLLATE " . $vv['Collation'] . " " ;
                if ($vv['Null'] == 'NO') $tStr .= ' NOT NULL' ;
                if ($vv['Default'] != NULL ) $tStr .= " DEFAULT '" . $vv['Default'] . "' " ;
                $tArr[] = $tStr;
            }

            $tCols = implode(',', $tArr);
            $tDrop = "DROP TABLE `" . $k . "` ;";
            $tCreate = "CREATE TABLE `" . $k . "` (
                " . $tCols . "
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci ;
            ";

            $sql[] = $tDrop;
            $sql[] = $tCreate;

        }
        return $sql;
    }

    /**
     * [getTableList 取得table與field清單].
     * 利用 SHOW TABLE STATUS 取得table清單
     *
     * @return array 回傳table欄位清單
     */
    function getTableList()
    {
        $filter_table = (array)$this->filter['table'];
        $filter_ex_table = (array)$this->filter['ex_table'];
        $filter_col_type = (array)$this->filter['col_type'];
        $filter_col_field = (array)$this->filter['col_field'];
        $filter_minrow = $this->filter['minrow'];
        $filter_maxrow = $this->filter['maxrow'];


        $tTables = $this->db_in->query('SHOW TABLE STATUS');
        $tables = [];
        $tArr = [];
        $i = 0;
        foreach ($tTables as $k => $v) {
            if (empty($v['Rows'])) {
                continue;
            };

            if ($v['Rows']<$filter_minrow and !empty($filter_minrow)) {
                continue;
            };
            if ($v['Rows']>$filter_maxrow and !empty($filter_maxrow)) {
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
