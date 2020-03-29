<?php
namespace Pulsestorm\Mysql\Keycheck;
use function Pulsestorm\Pestle\Importer\pestle_import;
pestle_import('Pulsestorm\Pestle\Library\output');
pestle_import('Pulsestorm\Pestle\Library\inputPassword');
pestle_import('Pulsestorm\Pestle\Library\exitWithErrorMessage');

function getTableNames($pdo)
{
    $result = $pdo->query('SHOW TABLES');        
    $tables = [];
    foreach($result as $row)
    {
        $tables[] = $row[0];
    }
    return $tables;
}

function extractForeignKeyLinesFromCreateTable($string)
{
    $lines = preg_split('%[\r\n]%', $string);
    $lines = array_filter($lines, function($line){ 
        return strpos($line, 'FOREIGN KEY') !== false && 
            strpos($line, 'CONSTRAINT') !== -1;;
        return false;
    });
    if(!$lines) 
    {
        return array();        
    }
    return $lines;
}

function extractForeignKey($pdo, $table)
{
    $result = $pdo->query('SHOW CREATE TABLE ' . $table);
    foreach($result as $row)
    {
        $create = $row['Create Table'];
    }
    $lines = extractForeignKeyLinesFromCreateTable($create);
    
    $keys = array_map(function($line){
        preg_match('%\s+CONSTRAINT `([^`]*)` FOREIGN KEY \(`([^`]*)`\) '
        . 'REFERENCES (`[^`]*\.)?`([^`]*)` \(`([^`]*)`\)'
        . '( ON DELETE (RESTRICT|CASCADE|SET NULL|NO ACTION))?'
        . '( ON UPDATE (RESTRICT|CASCADE|SET NULL|NO ACTION))?%',$line, $match);
        return [
            'foreign-key-name'  =>$match[1],
            'column-name'       => $match[2],
            'points-to-schema'  => $match[3],
            'points-to-table'   => $match[4],
            'points-to-column'  => $match[5],
            'on-delete'         => isset($match[6]) ? $match[7] : '',
            'on-update'         => isset($match[8]) ? $match[9] : ''            
        ];
    }, $lines);

    return $keys;
}

function extractForeignKeys($pdo, $tables)
{
    $tablesWithKeysAndKeys = [];
    foreach($tables as $table)
    {
        $tablesWithKeysAndKeys[$table] = extractForeignKey($pdo, $table);
    }    
    $tablesWithKeysAndKeys = array_filter($tablesWithKeysAndKeys);
    return $tablesWithKeysAndKeys;
}

function scanForInvalidData($pdo, $tablesToForeignKeys)
{
    $report = [];
    foreach($tablesToForeignKeys as $table=>$keys)
    {
        foreach($keys as $keyInfo)
        {
            $pointsToTable = $keyInfo['points-to-table'];
            if($keyInfo['points-to-schema'])
            {
                $pointsToTable = $keyInfo['points-to-schema'] . '.' . $pointsToTable;
            }
            //replace count(*) with '.$table.'.*
            $sql = '
                SELECT
                    '.$table.'.*
                FROM
                    '.$table.'
                WHERE
                    ' . $table . '.' . $keyInfo['column-name'] . ' IS NOT NULL AND
                    ' . $table . '.' . $keyInfo['column-name'] . ' NOT IN 
                        (SELECT ' . $pointsToTable . 
                                '.' . $keyInfo['points-to-column'] . ' 
                            FROM ' . $pointsToTable . ' 
                            WHERE ' . $pointsToTable . '.' . 
                                $keyInfo['points-to-column'] . ' IS NOT NULL)';
            
            $result = $pdo->query($sql);
            $reportKey = "$table.{$keyInfo['column-name']} points to $pointsToTable.{$keyInfo['points-to-column']}";
            
            $report[$reportKey] = [
                'count'=>$result->rowCount(),
                'query'=>$sql
            ];
        }
    }
    return $report;
}

function reportOnBadRows($tablesToForeignKeys)
{
    output("Invalid Foreign Key Value Counts");
    output('--------------------------------------------------');
    foreach($tablesToForeignKeys as $table=>$report)
    {
        if($report['count'] === 0) { continue; }
        output($report['count'] . "\t" . $table);
    }
    output('');
}

function reportOnSql($tablesToForeignKeys)
{
    output("SQL to Find Keys");
    output('--------------------------------------------------');
    output('');
    foreach($tablesToForeignKeys as $table=>$report)
    {
        if($report['count'] === 0) { continue; }
        output($table);
        output('+--------------------------------------------------+');
        output($report['query']);
        output('+--------------------------------------------------+');
        output('');
    }
}

/**
* Looks for Invalid Keys in a MySQL Database
*
* @command mysql:key-check
* @argument server DB Server? [127.0.0.1]
* @argument port DB Port? [3306]
* @argument username DB Username? [root]
* @argument schema Schema Name?
* @option use-sql-report Include SQL Statments in Reporting
*/
function pestle_cli($argv, $options)
{
    $server     = $argv['server'];
    $port       = $argv['port'];
    $username   = $argv['username'];
    $schema     = $argv['schema'];

    $password = inputPassword('MySQL Password: ');
    
    $pdo = new \PDO(
        'mysql:host='.$server.';dbname='.$schema, $username, $password);

    $tables = getTableNames($pdo);        
    $tablesToForeignKeys = extractForeignKeys($pdo, $tables);
    $tablesToForeignKeys = scanForInvalidData($pdo, $tablesToForeignKeys);
    
    output('');
    reportOnBadRows($tablesToForeignKeys);
    if($options['use-sql-report'] !== null)
    {
        reportOnSql($tablesToForeignKeys);
    }
    output('');
}
