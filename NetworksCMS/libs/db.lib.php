<?php
namespace NetWorks\libs;
include_once dirname(path: __DIR__).'/init.php';
use SQLite3;
class Database{
    protected SQLite3 $db;
    public const READ_ONLY = SQLITE3_OPEN_READONLY;
    public const OPEN_READWRITE = SQLITE3_OPEN_READWRITE;
    public const OPEN_CREATE = SQLITE3_OPEN_CREATE;
    public const ASSOC = SQLITE3_ASSOC;
    public const NUM = SQLITE3_NUM;
    public const BOTH = SQLITE3_BOTH;
    protected string|null $table=null;
    /**
     * Creates a database
     * @param string $file File name to save
     * @param int $flags Flags to trigger the database
     * @param string $key [Optional] - Encryption key
     */
    public function __construct(string $file, int $flags = Database::OPEN_READWRITE|Database::OPEN_CREATE, string $key='') {
        $this->db = new SQLite3(filename: NW_DATABASE.NW_DS.preg_replace(pattern: '/\..*$/',replacement: '',subject: trim(string: $file)).'.db',flags: $flags,encryptionKey: $key);
        $this->enableExpectations(enable: false);
    }
    /**
     * Enables expectations
     * @param bool $enable Sets the expectations enabled.
     * @return Database
     */
    public function enableExpectations(bool $enable=true): static{
        $this->db->enableExceptions(enable: $enable);
        return $this;
    }
    /**
     * Creates a table
     * @param string $name Tables Name
     * @param array{label: string} $options Options for each index. Example ["project_id"=>"INTEGER PRIMARY KEY"]
     * @return bool TRUE if the code was successful, else FALSE
     */
    public function createTable(string $name,array $options=[]): bool{
        $sql = "CREATE TABLE IF NOT EXISTS $name (";
        foreach($options as $label=>$settings){
            $sql.=trim(string: $label).' '.trim(string: $settings).',';
        }
        $sql = preg_replace(pattern: '/,$/',replacement: '',subject: $sql);
        $sql.=")";
        return $this->db->exec(query: $sql);
    }
    /**
     * Alter table
     * @param string|null $name Table name
     * @param string $alt Finish the rest ALTER TABLE $name **[Your Alternative Cmd...]**
     * @return bool
     */
    public function alterTable(string|null $name, string $alt=''): bool{
        $name = $this->table??$name;
        $sql = "ALTER TABLE $name $alt";
        return $this->db->exec(query: $sql);
    }
    /**
     * Drops table from database
     * @param string|null $name Tables name
     * @return bool TRUE if the code was successful, else FALSE
     */
    public function dropTable(string|null $name=null): bool{
        global $lang;
        $name = $this->table??$name;
        if(!$name) die($lang['noTableSelected']);
        $sql = "DROP TABLE IF EXISTS $name";
        return $this->db->exec(query: $sql);
    }
    /**
     * Selects a table
     * @param string $name Table name
     * @return Database
     */
    public function selectTable(string $name): static{
        $this->table = $name;
        return $this;
    }
    /**
     * Checks if table exists
     * @param string|null $name Tables name
     * @return bool TRUE if table exists, else FALSE
     */
    public function checkTable(string|null $name=null): bool{
        global $lang;
        $name = $this->table??$name;
        if(!$name) die($lang['noTableSelected']);
        $sql = "SELECT * FROM $name";
        return $this->db->query(query: $sql)->fetchArray() ? true : false;
    }
    /**
     * Selects data from table
     * @param string|null $name Tables name
     * @param string|array $selector Select specific information. Use __*__ to select all
     * @param string $conditions Conditions to simplify the search. **DO NOT INCLUDE "WHERE"**
     * @param int $mode Controls how the next row will be returned to the caller. This value must be one of either
     * @return bool|array The results of the selected query
     */
    public function select(string|null $name=null, string|array $selector='*' ,string $conditions='', int $mode=Database::BOTH): array|bool{
        global $lang;
        $name = $this->table??$name;
        if(!$name) die($lang['noTableSelected']);
        $sql = "SELECT ".(is_array(value: $selector) ? implode(separator: ',',array: $selector) : $selector)." FROM $name".($conditions ? " WHERE $conditions" : "");
        return $this->db->query(query: $sql)->fetchArray(mode: $mode);
    }
    /**
     * Inserts data into the table
     * @param string|null $name Tables name
     * @param array{key:string, value:mixed} $data Data to insert. Example: ['column'=>'value',...]
     * @return bool TRUE if the data has been inserted, else FALSE
     */
    public function insert(string|null $name=null, array $data): bool{
        global $lang;
        $name = $this->table??$name;
        if(!$name) die($lang['noTableSelected']);
        global $temp;
        $temp = [];
        $sql = "INSERT INTO $name (".implode(separator: ',',array: array_map(callback: function($e) use ($data): mixed{
            global $temp;
            $temp[":$e"] = $data[$e];
            return $e;
        },array: array_keys($data))).") VALUES (".implode(separator: ',',array: array_keys($temp)).")";
        $prep = $this->db->prepare(query: $sql);
        foreach($temp as $bind=>$value){
            $prep->bindValue(param: $bind,value: $value);
        }
        return $prep->execute() ? true : false;
    }
    /**
     * Delete columns from the table
     * @param string|null $name Tables name
     * @param string $conditions Conditions to specific target. **DO NOT INCLUDE "WHERE"**
     * @return bool TRUE if column has been deleted, else FALSE
     */
    public function delete(string|null $name=null, string $conditions=''): bool{
        global $lang;
        $name = $this->table??$name;
        if(!$name) die($lang['noTableSelected']);
        $sql = "DELETE FROM $name".($conditions ? " WHERE $conditions" : "");
        return $this->db->exec(query: $sql);
    }
    /**
     * Updates the data in  a column
     * @param array{column: string, value:mixed} $data Data to update. Example [column=>value,...]
     * @param string|null $name Tables name
     * @param string $conditions
     * @return bool
     */
    public function update(array $data, string|null $name=null, string $conditions=''):bool{
        global $lang;
        $name = $this->table??$name;
        global $temp;
        $temp=[];
        if(!$name) die($lang['noTableSelected']);
        
        array_map(callback: function($e): void{
            global $temp;
            $split = explode(separator: '=',string: $e);
            if(isset($e[1])){
                $temp[":$split[0]"] = $split[1];
            }
            return;
        },
        array: preg_split(pattern: '/ /',subject: preg_replace(pattern: '/ = /',replacement: '=',subject: trim(string: $conditions)))
        );
        $sql = "UPDATE $name SET ".implode(separator: ',',array: array_map(callback: function($e) use ($data): string{
            global $temp;
            if(gettype(value: $data[$e])==='string'){
                $data[$e] = "{$data[$e]}";
            }
            if(is_bool(value: $data[$e])){
                $data[$e] = $data[$e] ? 1 : 0;
            }
            $temp[":$e"] = $data[$e];
            
            return "$e=:$e";
        },array: array_keys($data))).($conditions ? " WHERE ".preg_replace_callback(pattern: "/\S+/",callback: function($e): string{
            $s = explode(separator: '=',string: $e[0]);
            if(isset($s[1])) return "$s[0]=:$s[0]";
            else return $s[0];
        },subject: $conditions) : '');
        $prep = $this->db->prepare(query: $sql);
        global $temp;
        foreach($temp as $bind=>$value){
            $prep->bindValue(param: $bind, value: $value);
        }
        return $prep->execute() ? true : false;
    }

    /**
     * Closes the connection
     * @return bool TRUE if successfully closed, else FALSE
     */
    public function close(): bool{
        return $this->db->close();
    }
}
?>