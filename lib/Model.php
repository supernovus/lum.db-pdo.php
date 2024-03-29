<?php

namespace Lum\DB\PDO;

// TODO: make more things support the as_name table aliases.
// TODO: persistent column aliases? [Might be useful.]

use \Lum\Compat as LC;

/**
 * An object oriented database model library.
 */
abstract class Model implements \Iterator, \ArrayAccess
{
  use \Lum\DB\ModelCommon, \Lum\Meta\ClassID; 

  protected $table;          // Our database table.
  protected $childclass;     // The class name for our children.
  protected $resultclass;    // Class name for iterable result set. 

  protected $primary_key = 'id'; // The primary key on the database table.
                                 // Defaults to 'id' if not specified.

  protected $db;             // A Lum\DB\PDO\Simple object.

  // Change to true to coerce boolean on construction.
  protected $coerce_boolean;

  protected $resultset;      // Used if you use the iterator interface.

  public $parent;            // The object which spawned us.

  protected $get_fields;     // Set to fields to get if none are specified.

  public $default_value = null; // Fields will be set to this by default.

  protected $as_name; // Optional AS name for implicit joins.

  protected $serialize_ignore = ['resultset']; // Ignore when serializing.

  const return_row = 1; // Return a proper Row object.
  const return_raw = 2; // Return a raw DB query object.
  const return_key = 3; // Return the primary key value.

  /**
   * Build a new Model object.
   */
  public function __construct ($opts=array(), $simpleInstance=null)
  {
    // Ensure our options are an array.
    if (is_string($opts))
    {
      $opts = Simple::load_conf($opts);
    }

    // Build our Simple DB object.
    // It will throw an exception of there are missing parameters.
    if (isset($simpleInstance) && $simpleInstance instanceof Simple)
    {
      $this->db = $simpleInstance;
    }
    else
    {
      $keepConfig = isset($opts['keepConfig']) ? $opts['keepConfig'] : true;
      $this->db = new Simple($opts, $keepConfig);
    }

    if (isset($this->coerce_boolean))
    {
      $this->coerce_boolean($this->coerce_boolean);
    }

    // Initialize our classid that is passed from the module loader.
    if (isset($opts['__classid']))
      $this->__classid = $opts['__classid'];

    // Add our parent class, usually the current Controller.
    if (isset($opts['parent']))
      $this->parent = $opts['parent'];

    // Check for a bunch of other options.

    if (isset($opts['table']))
      $this->table = $opts['table'];
    elseif (!isset($this->table))
      $this->table = $this->name();
    if (isset($opts['childclass']))
      $this->childclass = $opts['childclass'];
    if (isset($opts['resultclass']))
      $this->resultclass = $opts['resultclass'];
    if (isset($opts['primary_key']))
      $this->primary_key = $opts['primary_key'];

  }

  public function __sleep ()
  {
    $properties = get_object_vars($this);
    foreach ($this->serialize_ignore as $ignored)
    {
      unset($properties[$ignored]);
    }
    unset($this->db->db); // Delete the PDO object.
    return array_keys($properties);
  }

  public function __wakeup ()
  {
    $this->db->reconnect();
  }

  public function toggle_debug ($toggle=null)
  {
    if (isset($toggle) && is_bool($toggle))
    {
      $this->db->debugging = $toggle;
    }
    else
    {
      $this->db->debugging = $this->db->debugging ? false : true;
    }
  }

  public function coerce_boolean ($toggle=null)
  {
    if (isset($toggle) && is_bool($toggle))
    {
      $this->db->coerce_boolean = $toggle;
    }
    else
    {
      $this->db->coerce_boolean = $this->db->coerce_boolean ? false : true;
    }
  }

  public function toggle_strict ($toggle=null)
  {
    if (isset($toggle) && is_bool($toggle))
    {
      $this->db->strict = $toggle;
    }
    else
    {
      $this->db->strict = $this->db->strict ? false : true;
    }
  }

  /**
   * Return our table name. Unlike ref() this ALWAYS returns just the table
   * name, with no AS statement!
   */
  public function get_table ()
  {
    return $this->table;
  }

  /**
   * Return a reference name for use in implicit joins.
   *
   * @param string $colName (Optional) Name of the column we are referencing.
   * @param string $asName (Optional) Alias name for the column.
   *
   * @return string  If $colName was passed, this will be our table name,
   *                 or table alias, along with the column name, or column alias.
   *
   *                 If no $colName was passed, this will be the table name, or
   *                 a string in "table_name AS table_alias".
   *
   *                 NOTE: The $asName passed here only ever applies to the
   *                 column. If you want to set a table alias, see asName().
   */ 
  public function refName ($colName=null, $asName=null)
  {
    if (is_null($colName))
    { // Get the reference name of our table.
      if (isset($this->as_name))
      {
        return $this->table . ' AS '. $this->as_name;
      }
      else
      {
        return $this->table;
      }
    }
    else
    { // Get the reference name of a column, applying our table alias if set.
      if (isset($this->as_name))
      {
        $ref = $this->as_name;
      }
      else
      {
        $ref = $this->table;
      }
      $ref .= '.' . $colName;
      if (isset($asName))
      {
        $ref .= ' AS ' . $asName;
      }
      return $ref;
    }
  }

  /**
   * Return a Reference object representing a column in our table.
   * Used in queries using implicit joins.
   *
   * @param string $column The column we are querying, may be an alias if used.
   *
   * @return Reference An instance of the Reference class.
   */
  public function refData ($column)
  {
    if (isset($this->as_name))
    {
      $table = $this->as_name;
    }
    else
    {
      $table = $this->table;
    }
    return new Reference($table, $column, $this);
  }

  /**
   * Get or set the table alias name for implicit joins.
   *
   * @param string $name (Optional) If passed, set the alias name to this.
   *
   * @return mixed If $name was specified, returns $this, otherwise returns the
   *               current table alias name.
   */
  public function asName ($name=null)
  {
    if (isset($name))
    {
      $this->as_name = $name;
      return $this;
    }
    else
    {
      return $this->as_name;
    }
  }

  /**
   * Return the name of our class.
   *
   * This depends on the use of the Lum Models loader.
   * If you are not using the models loader, override this.
   */
  public function name ()
  {
    return $this->__classid;
  }

  /**
   * Look up the database name. We will cache the result.
   */
  public function database_name ()
  {
    return $this->db->name;
  }

  /** 
   * Wrap the results of fetch() in a nice object.
   *
   * If the hash is not set, or is false it returns null.
   * If our childclass is false, we return the unchanged row.
   */
  public function wrapRow ($rowhash, $opts=[])
  { 
    if (isset($opts['rawRow']) && $opts['rawRow'])
      return $rowhash;
    if ($rowhash)
    {
      $object = $this->newChild($rowhash, $opts);
      if (isset($object))
        return $object;
      else
        return $rowhash;
    }
  }

  /** 
   * Get an instance of our child class, representing a row.
   *
   * This is used by wrapRow() for existing rows, and can be used directly
   * to return a child row with no primary key set, that can be inserted into
   * the database using $row->save();
   *
   * If the known_fields class member is set, it will be used to ensure
   * all of the known fields are passed to the child class with some form
   * of default value (if not specified in the known_fields array, the
   * default value depends on the $this->default_null setting.)
   */
  public function newChild ($data=[], $opts=[])
  {
    $data = $this->populate_known_fields($data);
    if (isset($opts['childclass']))
      $class = $opts['childclass'];
    else
      $class = $this->childclass;

    if ($class)
    {
      $opts['parent'] = $this;
      $opts['data']   = $data;
      $opts['pk']     = $this->primary_key;
      $opts['table']  = $this->table;
      $object = new $class($opts);
      return $object;
    }
  }

  /** 
   * Return a ResultSet object. It will dynamically retreive the results.
   *
   * If it cannot find the resultclass, it instead executes the statement
   * and returns the PDOResult object.
   */
  public function getResults ($query, $opts=[])
  {
    if (isset($query['resultclass']))
      $class = $query['resultclass'];
    elseif (isset($opts['resultclass']))
      $class = $query['resultclass'];
    else
      $class = $this->resultclass;

    if ($class && (!isset($opts['rawResults']) || !$opts['rawResults']))
    {
      $opts['parent'] = $this;
      $opts['pk'] = $this->primary_key;
      $opts['query'] = $query;
      return new $class($opts);
    }
    return $this->selectQuery($query, $opts);
  }

  /**
   * Select data.
   */
  public function select ($query=[], $opts=[])
  {
    if (is_array($query))
    {
      if (isset($query['childclass']))
        $opts['childclass'] = $query['childclass'];
      if (isset($query['rawResults']))
        $opts['rawResults'] = $query['rawResults'];
    }
    elseif (is_object($query))
    {
      $pval = get_query_property($query, 'childclass');
      if (isset($pval))
        $opts['childclass'] = $pval;
      $pval = get_query_property($query, 'rawResults');
      if (isset($pval))
        $opts['rawResults'] = $pval;
    }
    if ($this->db->connected() && (!isset($query['single']) || !$query['single']))
    {
      return $this->getResults($query, $opts);
    }
    return $this->selectQuery($query, $opts);
  }

  /**
   * Generate an implicit join table array.
   */
  protected function implicit_join_tables ($inTables)
  {
    if (is_string($inTables))
    { // A string list? Not common, but okay. Be careful with this!
      if (strpos($inTables, $this->table) === FALSE)
      {
        $outTables = $this->refName() . ',' . $inTables;
      }
      else
      {
        $outTables = $inTables;
      }
    }
    elseif (is_array($inTables))
    { // Array list, this is the recommended approach.
      $outTables = [$this->refName()];
      foreach ($inTables as $inTable)
      {
        if (is_object($inTable) && is_callable([$inTable, 'refName']))
        { // Another Model instance, this is the best.
          $outTables[] = $inTable->refName();
        }
        elseif (is_string($inTable))
        { // A hard coded table string, not recommended, but okay.
          $outTables[] = $inTable;
        }
        else
        {
          error_log("Invalid implicit join table definition: ".serialize($inTable));
        }
      }
    }
    else
    {
      error_log("Invalid implicit join tables: ".serialize($inTables));
      $outTables = $this->refName();
    }

    return $outTables;
  }

  /**
   * The underlying select wrapper.
   */
  public function selectQuery ($query=[], $opts=[])
  {
    $table = null;
    if (is_array($query) && isset($query['with']))
    {
      $table = $this->implicit_join_tables($query['with']);
      if (!isset($query['cols']))
      {
        $query['cols'] = $this->refName() . '.*';
      }
    }
    elseif (is_object($query))
    {
      $pval = get_query_property($query, 'with');
      if (isset($pval))
      {
        $table = $this->implicit_join_tables($pval);
      }
      $pval = get_query_property($query, 'cols');
      if (!isset($pval))
      {
        error_log("No 'cols' defined in Query but implicit join in use!");
      }
    }
    if (!isset($table))
    { // Get our table name from the ref() method.
      $table = $this->refName();
    }

    $result = $this->db->select($table, $query);
    $raw = false;
    if (!$this->db->connected())
      $raw = true;
    elseif (isset($opts['rawRow']) && $opts['rawRow'])
      $raw = true;
    elseif (is_array($query) && isset($query['rawRow']) && $query['rawRow'])
      $raw = true;
    elseif (is_object($query))
    {
      $pval = get_query_property($query, 'rawRow');
      if ($pval)
      {
        $raw = true;
      }
      else
      {
        $pval = get_query_property($query, 'raw');
        if ($pval)
          $raw = true;
      }
    }
    if ($raw)
    {
      return $result;
    }
    else
    {
      return $this->wrapRow($result, $opts);
    }
  }

  /** 
   * Get a single row based on the value of the primary key.
   */
  public function getRowById ($id, $ashash=False, $cols=null)
  {
    $want = 
    [
      'where'  => [$this->primary_key => $id], 
      'rawRow' => $ashash, 
      'single' => true,
    ];
    if (isset($cols))
      $want['cols'] = $cols;
    return $this->select($want);
  }

  /**
   * Get rows with a WHERE statement.
   */
  public function getRowsWhere ($where, $data=null, $want=[], $single=false)
  {
    if (!is_array($want))
    {
      LC::deprecate("getRowsWhere() want must be an array not a "
        . get_debug_type($want));
      $want = [];
    }
    if (is_string($where) && is_array($data))
    {
      $want['data'] = $data;
    }
    elseif (is_array($data) && count($want) == 0)
    {
      $want = $data;
    }
    $want['where'] = $where;
    if ($single)
    {
      $want['single'] = true;
    }
    return $this->select($want);
  }

  /**
   * Get a bunch of rows by column values.
   */
  public function getRowWhere ($where, $data=null, $want=[])
  {
    return $this->getRowsWhere($where, $data, $want, true);
  }

  /** 
   * Return a ResultSet (or other result class) for all rows in our table.
   */
  public function all ()
  {
    return $this->select();
  }

  /** 
   * Insert a new row. 
   *
   * Note this does no checking to ensure
   * the specified fields are valid, so wrap this in your own
   * classes with more specific versions. It's also not recommended
   * that you include the primary key field, as it should be auto
   * generated by the database. To this end, by default we disallow
   * the primary key field. As the output of an insert is not consistent
   * we just return the query object, if you're at all interested.
   */
  public function insert ($row, $opts=[])
  { // Check for options.
    $res = $this->db->insert($this->table, $row);
    if (!$this->db->connected()) return $res;

    list($stmt,$fielddata) = $res;
    if (isset($opts['return']))
    {
      $pk = $this->primary_key;
      $return_type = $opts['return'];
      if (isset($opts['cols']) && is_array($opts['cols']))
      {
        $fields = [];
        foreach ($opts['cols'] as $colname)
        {
          $fields[$colname] = $fielddata[$colname];
        }
      }
      else
      {
        $fields = $fielddata;
        unset($fields[$pk]);
      }
      $what = ['where'=>$fields, 'single'=>true];
      if ($return_type == $this::return_row)
      {
#        error_log("Return Row object: ".json_encode($fields));
        return $this->select($what);
      }
      elseif ($return_type == $this::return_raw)
      {
        $what['rawRow'] = true;
        return $this->select($what); 
      }
      elseif ($return_type == $this::return_key)
      {
        $pk = $this->primary_key;
#        error_log("fields: ".json_encode($fields));
#        error_log("pk: $pk");
        $what['rawRow'] = true;
        $what['cols']   = $pk;
        $rawrow = $this->select($what);
#        error_log("rawrow: ".json_encode($rawrow));
        if (isset($rawrow) && isset($rawrow[$pk]))
        {
          return $rawrow[$pk];
        }
        else
        {
          return Null;
        }
      }
    }
    return $stmt;
  }

  /**
   * Update row(s).
   */
  public function update ($where, $cdata=null, $wdata=null)
  {
    return $this->db->update($this->table, $where, $cdata, $wdata);
  }

  /**
   * Delete row(s).
   */
  public function delete ($where, $wdata=null)
  {
    return $this->db->delete($this->table, $where, $wdata);
  }

  /**
   * Generate a prepared statement.
   */
  public function query ($statement)
  {
    return $this->db->query($statement);
  }

  /**
   * Return a row count
   */
  public function rowcount ($where=null, $data=[], $colname='*')
  {
    return $this->db->rowcount($this->table, $where, $data, $colname);
  }

  // Iterator interface to use a DBModel in a foreach loop.
  // If you attempt to use this with resultclass set to false,
  // things will break, badly. Just don't do it.

  public function rewind (): void
  {
    $this->resultset = $this->all();
    $this->resultset->rewind();
  }

  public function current (): mixed
  {
    return $this->resultset->current();
  }

  public function next (): void
  {
    $this->resultset->next();
  }

  public function key (): mixed
  {
    return $this->resultset->key();
  }

  public function valid (): bool
  {
    return $this->resultset->valid();
  }

  // ArrayAccess interface for easier querying.

  public function offsetGet ($offset): mixed
  {
    return $this->getRowById($offset);
  }

  public function offsetExists ($offset): bool
  {
    $row = $this->getRowById($offset);
    if ($row)
      return True;
    else
      return False;
  }

  public function offsetUnset ($offset): void
  {
    $pk = $this->primary_key;
    $this->delete([$pk=>$offset]);
  }

  public function offsetSet ($offset, $value): void
  {
    throw new \Exception('You cannot set DB Model values that way.');
  }

  // Simple row cloning.
  public function cloneRow ($rowid)
  {
    $pk  = $this->primary_key;
    $row = $this->getRowById($rowid);
    if (isset($row))
    {
      unset($row[$pk]);
      if (is_callable(array($row, 'updateClone')))
      {
        $row->updateClone();
      }
      if (is_callable(array($row, 'save')))
      {
        $row->save();
      }
    }
    else
    {
      return Null;
    }
    return $row;
  }

} // end class Model

// End of library.
