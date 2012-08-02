<?

class ActiveRecord 
{
  var $is_new=true;
  var $errors=array();
  static $eager_load = array();
  
  static function add_function($func_name, $callback)
  {
    static::$functions[$func_name] = $callback;
  }
  
  static function add_property($prop_name, $callback, $is_determinstic = false)
  {
    static::$properties[$prop_name] = $callback;
  }
  
  function responds_to($name)
  {
    return activerecord_responds_to($this->klass, $name);
    
  }
  
  
  function __get  ($name)
  {
    if(isset(static::$properties[$name]))
    {
      return call_user_func(static::$properties[$name], $this);
    }
    
    if (array_key_exists($name, static::$belongs_to))
    {
      $this->a($name);
      return $this->$name;
    }
    if (array_key_exists($name, static::$has_many))
    {
      $this->a($name);
      return $this->$name;
    }
    if (array_key_exists($name, static::$has_many_through))
    {
      $this->a($name);
      return $this->$name;
    }
    
    $hms = static::$has_many;
    foreach($hms as $hm=>$arr)
    {
      $fk = $arr[1];
      $tn = W::singularize($hm);
      $kn = W::classify(W::singularize($arr[0]));
      if(preg_match("/^{$hm}_count$/", $name, $matches))
      {
        return get_model_count($o, $kn, $fk);
      }
    }
    
    if(preg_match("/^is_(.+)_dirty$/",$name,$matches))
    {
      list($junk,$prop_name) = $matches;
      $ov = "{$prop_name}_original_value";
      return $this->$prop_name != $o->$ov;
    }
  
    W::error("No getter defined $f");
	}
	
	function __call($name, $args)
	{
    array_unshift($args, $this);
    if(isset(static::$functions[$name]))
    {
      return call_user_func_array(static::$functions[$name], $args);
    }
    
    $hms = static::$has_many;
    if(preg_match('/^find_(.+)_by_(.+)$/', $name, $matches))
    {
      list($junk,$hm_name, $prop_name) = $matches;
      if(array_key_exists($hm_name,$hms))
      {
        $val = array_shift($arguments);
        $sort_by = null;
        if($arguments) $sort_by = array_shift($arguments);
        $v = get_collection_members_by_prop_val($this->$hm_name, $prop_name, $val, $sort_by);
      } else {
        list($val) = $arguments;
        $hm_name = pluralize($hm_name);
        $v = get_collection_member_by_prop($this->$hm_name, $prop_name, $val);
      }
      return $v;
    }

    if(preg_match('/^purge(.+)$/', $name, $matches))
    {
      list($junk,$prop_name) = $matches;
      $this->purge($prop_name);
      return;
    }

    if(isset($hms[$name]))
    {
      $params = array();
      if(count($arguments)>0) $params = array_shift($arguments);
      $params = ActiveRecord::add_condition($params, "{$hms[$name][1]} = ?", $this->id);
      $objs = ActiveRecord::_find_all(classify(singularize($hms[$name][0])), $params);
      return $objs;
    }
    
    $hmt = static::$has_many_through;
    if(isset($hmt[$name]))
    {
/*
      $o->a($name);
      return $o->$name;
*/
      list($junction_table_name, $fk_name) = $hmt[$name];
      $params = array();
      if(count($arguments)>0) $params = array_shift($arguments);
      $table_name = static::$table_name;
      $params['joins'] = "join $junction_table_name r on r.{$fk_name}_id = {$name}.id and r.user_id = {$o->id}";
      $objs = ActiveRecord::_find_all(classify(singularize($name)), $params);
      return $objs;
    }
        
    W::error("No function $f()");	 
	}

  
  static function _create_or_update_by($klass, $params=array())
  {
  	$o = static::$find_or_new_by($params);
  	$o->save();
  	return $o;
  }
  
  function __construct($params=array())
  {
  	$this->is_valid=true;
	  $an = static::$attribute_names;
  	foreach($an as $k)
  	{
  	  $ms = static::$model_settings;
  		$this->$k = $ms['default_value'][$k];
  		$this->_format($k);
  		$thisv = "{$k}_original_value";
  		$this->$thisv = $this->$k;
  	}
  	
  	$this->params = $params;
  	if (array_key_exists('attributes', $params))
  	{
      $this->update_attributes($params['attributes'], false);
  	}
  	
  	$this->event('after_new');
  }

  
  static function _bulk_create($klass, $params_arr=array())
  {
    $objs = array();
    foreach($params_arr as $params)
    {
      $objs[] = eval("return {$klass}::create(\$params);");
    }
  }

  static function _create($klass, $params=array())
  {
    global $queries;
  	$o = new $klass($params);
  	$o->params = $params;
  	if (!isset($params['attributes'])) $params['attributes'] = array(); 
  	$o->update_attributes($params['attributes']);
  	$old_o = $o;
  	if (!$o->is_valid)
  	{
      W::error("Attempt to create invalid model.", $o);
    }
    $o = $o->reload();
    if (!$o) W::error("Failed to reload object. Should never happen.", array($old_o, $klass, $params, $queries));
    $o->event('after_create');
  	return $o;
  }
  
  function event($event_name, $event_args = array())
  {
    $obj_name = W::singularize(W::tableize($this->klass));
    $event_name = "{$obj_name}_{$event_name}";
    W::action($event_name, $this, $event_args);
  }
  
  static function _find($klass, $params=array())
  {
  	$params = ActiveRecord::construct_params($klass, $params);
  	$params['limit']=1;
  	$arr = ActiveRecord::_select_assoc($klass,$params);
  	if (count($arr)==0) return null;
  	$o = new $klass();
  	$o->is_new=false;
  	$o->update_attributes($arr[0],false);
  	$o->params = $params;
  	if ($params['load'])
  	{
  	  $recs = array($o);
  	  ActiveRecord::eager_load_associated($klass, $recs, $params['load']);
  	  $o = $recs[0];
  	}
  
  	$o->_after_load($klass);
  	return $o;
  }


  
  static function _select_assoc($klass, $params)
  {
    $tn = W::singularize(W::tableize($klass));
    $event_name = "{$tn}_before_select";
    $params = W::filter($event_name, $params);
    
  	$tn = ActiveRecord::_model_table_name($klass);
    $columns = "$tn.*";
    $joins = '';
    $where = '';
    $limit = '';
    $order = '';
    if(array_key_exists('columns', $params)) $columns = $params['columns'];
  	if(array_key_exists('joins', $params)) $joins = $params['joins'];
  	if(array_key_exists('conditions', $params)) $where = 'where ' . $params['conditions'];
  	if(array_key_exists('limit', $params)) $limit = 'limit ' . $params['limit'];
  	if(array_key_exists('order', $params)) $order = 'order by ' . $params['order'];
  	return W::db_query_assoc("select $columns from $tn $joins $where $order $limit");
  }
  

  

  static function _delete_all($klass, $params=array())
  {
  	extract($params);
  	$objs = eval("return $klass::find_all(\$params);");
  
  	foreach($objs as $obj)
  	{
  		$obj->event('before_delete');
  	}
  	$where = 'where 1=1';
  	if(array_key_exists('conditions', $params)) $where = 'where ' . $params['conditions'];
  	$tn = ActiveRecord::_model_table_name($klass);
  	W::db_query("delete from $tn $where");
  }
  
  function delete()
  {
    $this->event('before_delete');
    $tn = ActiveRecord::_model_table_name($this->klass); 
    $sql = "delete from $tn where {$this->pk()}={$this->id()}";
    $this->last_query = $sql;
    W::db_query($sql);
    $this->event('after_delete');
  }
  
  function reload()
  {
  	$klass=$this->klass;
  	$php = "return $klass::find_by_{$this->pk()}(\$this->id());";
  	$obj = eval($php);
  	$obj->params = $this->params;
  	return $obj;
  }

  
  static function _find_or_create_by($klass, $params=array())
  {
  	$params = ActiveRecord::construct_params($klass, $params);
  	$o = eval("return $klass::find(\$params);");
  	if (!$o)
  	{
      $o = eval("return $klass::create(\$params);");
  	}
  	return $o;
  }
  
  static function _count($klass, $params)
  {
    $unsets = array('columns', 'limit', 'columns');
    foreach($unsets as $unset) unset($params[$unset]);
    $params['columns'] = "count(id) c";
  	$params = ActiveRecord::construct_params($klass, $params);
    $res = ActiveRecord::_select_assoc($klass, $params);
    return $res[0]['c'];
  }
  
  static function construct_params($klass, $params)
  {
	  $allowed_params = array('columns', 'joins', 'attributes', 'conditions', 'data', 'limit', 'load', 'current_page', 'page_size', 'total', 'total_pages', 'order', 'search', 'post_filters');
	  foreach(array_keys($params) as $key) if(array_search($key, $allowed_params)===FALSE) W::error("Unrecognized ActiveRecord parameter $key in $klass query.", $params);
  	if ($params) extract($params);
  	$options = array('columns', 'joins', 'attributes', 'conditions', 'data', 'limit');
  	foreach($options as $option) eval("if (isset(\$$option) && \$$option) \$params['$option'] = \$$option;");
    if(!isset($params['post_filters'])) $params['post_filters'] = array();
  	if (!isset($order))
  	{
  	  $order = eval("return $klass::order_by();");
  	}
  	if (array_key_exists('current_page', $params) && !array_key_exists('total_pages', $params))
  	{
      if (!array_key_exists('page_size', $params)) $params['page_size']=10;
      $page_size=$params['page_size'];
      $pag_params = $params;
      unset($pag_params['current_page']);
      unset($pag_params['order']);
      $count = ActiveRecord::_count($klass, $pag_params);
      
      $total_pages = (int)(max(1,ceil($count/$page_size)));
      $current_page = max(1,min($total_pages, $params['current_page']));
      $params['limit']= ($current_page - 1) * $page_size.',' .$page_size;
      $params['total'] = $count;
      $params['total_pages'] = $total_pages;
  	}
  	if (array_key_exists('search', $params))
  	{
      $text = explode(' ', preg_replace("/[^A-Za-z0-9\$]/", " ", $params['search']));
      foreach($text as &$word) $word = "+".$word;
      $text = join(' ', $text);
      $text = ActiveRecord::sanitize($text);
      $table_name = W::tableize($klass);
      
      $params = ActiveRecord::add_condition(
        $params,
        "id in (!)",
        "SELECT record_id FROM search WHERE MATCH (search_text) AGAINST ('$text' IN boolean MODE) AND model_name = '$klass'"
      );
      unset($params['search']);
   	}
  	if (array_key_exists('joins', $params) && is_array($params['joins']))
  	{
      $params['joins'] = self::substitute($params['joins']);
  	}
  	if (array_key_exists('conditions', $params) && is_array($params['conditions']))
  	{
      $params['conditions'] = self::substitute($params['conditions']);
  	}
  	if ($order && strlen($order)>0) $params['order'] = $order;
  	if (!isset($load)) $load = array();
  	if (!is_array($load)) $load=array($load);
  	$params['load'] = array_merge($load, static::$eager_load);
  	return $params;
  }
  
  static function substitute($arr)
  {
		$phrase = array_shift($arr);
		$conditions = $arr;
	  $s = '';
	  for($i=0;$i<strlen($phrase);$i++)
	  {
		  if(count($conditions)==0)
		  {
		    $s .= substr($phrase, $i);
		    break;
		  }
	    $c = substr($phrase, $i, 1);
		  switch($c)
		  {
		    case '?':
		      $s .= self::quote(array_shift($conditions));
		      break;
		    case '!':
		      $s.= array_shift($conditions);
		      break;
		    case '@':
		      $s .= self::quote(self::db_date(array_shift($conditions)));
		      break;
		    default:
		      $s .= $c;
		  }
	  }
	  return $s;
  }
  
  static function _find_or_new_by($klass, $params=array())
  {
  	$params = ActiveRecord::construct_params($klass, $params);
  	$o = eval("return $klass::find(\$params);");
  	if (!$o)
  	{
  		$o = new $klass($params);
      $o->update_attributes($params['attributes'],false);
  	}
  	return $o;
  }
  
  function id()
  {
  	$ms = static::$model_settings;
  	$pk = $ms['pk'];
  	if(func_num_args()>0)
  	{
      $this->$pk = func_get_arg(0);
  	}
  	return $this->$pk;
  }

  function pk()
  {
  	$ms = static::$model_settings;
  	return $ms['pk'];
  }
  
  static function _find_all($klass, $params=array())
  {
  	$params = ActiveRecord::construct_params($klass, $params);
  	$arr = ActiveRecord::_select_assoc($klass, $params);
  	$recs=array();
  	$ms = static::$model_settings;
  	foreach($arr as $rec)
  	{
  		$o = new $klass();
  		$o->is_new=false;
  		$o->update_attributes($rec,false);
  		$o->params = $params;
    	if ($ms['is_auto_increment'][$o->pk()])
    	{
        $recs[$o->id()] = $o;
      } else {
    		$recs[] = $o;
      }
  	}
  
  	if(count($params['post_filters'])>0)
  	{
    	$new_recs = array();
    	foreach($recs as $r)
    	{
    	 $should_keep = true;
    	 foreach($params['post_filters'] as $filter)
    	 {
    	   $should_keep &= call_user_func($filter, $r);
    	   if(!$should_keep) break;
    	 }
    	 if($should_keep)
    	 {
    	   $new_recs[] = $r;
    	 }
    	}
    	$recs = $new_recs;
    }

  	if ($params['load'])
  	{
  	  ActiveRecord::eager_load_associated($klass, $recs, $params['load']);
  	}
  	
  	foreach($recs as $rec)
  	{
  		$rec->_after_load($klass);
  	}
  
  	return $recs;
  }

  static function _paginate($klass, $params=array(), $page=1, $items=20, &$pages)
  {
  	$params = ActiveRecord::construct_params($klass, $params);
  	$arr = ActiveRecord::_select_assoc($klass, $params);
  	$recs=array();
  	foreach($arr as $rec)
  	{
  		$o = new $klass();
  		$o->is_new=false;
  		$o->update_attributes($rec,false);
  		$o->params = $params;
  		$recs[] = $o;
  	}
  
    $pages = max(1,ceil(count($recs)/$items));
    if ($page < 1) 
    { 
    $page = 1; 
    } 
    elseif ($page > $pages) 
    { 
    $page = $pages; 
    } 
    $params['limit']= ($page - 1) * $items.',' .$items;
  	$params = ActiveRecord::construct_params($klass, $params);
  	$arr = ActiveRecord::_select_assoc($klass, $params);
  	$recs=array();
  	foreach($arr as $rec)
  	{
  		$o = new $klass();
  		$o->is_new=false;
  		$o->update_attributes($rec,false);
  		$o->params = $params;
  		$recs[] = $o;
  	}
    
    
  	if ($params['load'])
  	{
  	  ActiveRecord::eager_load_associated($klass, $recs, $params['load']);
  	}
  	
  	
  	for($i=0;$i<count($recs);$i++)
  	{
  		$recs[$i]->_after_load($klass);
  	}
  
  	return $recs;
  }
  
  static function eager_load_associated($klass,&$objs, $assocs)
  {
    if(count($objs)==0) return;
    if (!is_array($assocs)) $assocs = array($assocs);
    $current_assocs = keys($assocs);
    $arr = eval("return $klass::\$belongs_to;");
    if (!is_array($arr)) $arr = array($arr);
    foreach($current_assocs as $assoc)
    {
      foreach($objs as $obj)
      {
        $obj->$assoc = null;
      }
      
    }

    foreach($arr as $bt_alias => $bt_array)
  	{
  	  if (array_search ($bt_alias, $current_assocs)===FALSE) continue;
      list(
        $bt_klass,
        $bt_fk
      ) = $bt_array;
      $ids=W::array_collect($objs,$bt_fk);
      if (count($ids)==0) continue;

      $ids = array_map("ActiveRecord::sanitize", $ids);
      $ids = array_wrap($ids, "'");
      $ids = join(array_unique($ids),',');
      $params = array(
        'conditions'=>array("id in (!)", $ids)
      );
      if (array_key_exists($bt_alias, $assocs)) $params['load'] = $assocs[$bt_alias];
      
      $assoc_objs = eval("return $bt_klass::find_all(\$params);");

      if (is_array($assocs) && array_key_exists($bt_alias, $assocs))
      {
        ActiveRecord::eager_load_associated($bt_alias, $assoc_objs, $assocs[$bt_alias]);
      }

      foreach($objs as $k=>$v)
      {
        $v->$bt_alias = null;
        foreach($assoc_objs as $assoc_obj)
        {
          if ($v->$bt_fk == $assoc_obj->id()) 
          {
            $v->$bt_alias = $assoc_obj;
          }
        }
      }
  	}
  	
    foreach(eval("return $klass::\$has_many;") as $hm_alias=>$hm_array)
  	{
  	  $hm = $hm_array[0];
  	  $hm_fk = $hm_array[1];
  	  
      if (array_search ($hm_alias, $current_assocs)===FALSE) continue;
  

      $hm_klass = W::classify(W::singularize($hm));
  
      $ids=W::array_collect($objs,'id', $hm);
      
      for($i=0;$i<count($objs);$i++)
      {
      	$objs[$i]->$hm_alias = array();
      }
      
      if (count($ids)==0) continue;
      
      $ids = join(array_unique($ids),',');
  
      $params = array(
        'conditions'=>"$hm_fk in ($ids)"
      );
      if (array_key_exists($hm_alias, $assocs)) $params['load'] = $assocs[$hm_alias];
      $hm_objs = eval("return $hm_klass::find_all(\$params);");

      foreach($objs as $k=>$v)
      {
  			foreach($hm_objs as $assoc_obj)
  			{
          if ($v->id() == $assoc_obj->$hm_fk)
          {
            array_push($v->$hm_alias, $assoc_obj);
          }
        }
      }
  	}			

    foreach(eval("return $klass::\$has_many_through;") as $hmt_alias=>$hmt_info)
  	{
  	  list($hm_assoc, $bt_assoc) = $hmt_info;
  	  if (array_search ($hmt_alias, $current_assocs)===FALSE) continue;
  	  foreach($objs as $obj) $obj->$hmt_alias = array();

      $ids=W::array_collect($objs,'id');
      if (count($ids)==0) continue;

      list(
        $hm_klass,
        $hm_fk
      ) = eval("return $klass::\$has_many['$hm_assoc'];");

      $hm_table_name = static::$table_name;
      
      list(
        $bt_klass,
        $bt_fk
      ) = eval("return $hm_klass::\$belongs_to['$bt_assoc'];");
      $bt_table_name = static::$table_name;

      $ids = join(array_unique($ids),',');
      $params = array(
        'columns'=>"$bt_table_name.*, $hm_table_name.$hm_fk as __rel_id",
        'conditions'=>"$hm_table_name.$hm_fk in ($ids)",
        'joins'=>"join $hm_table_name on $hm_table_name.$bt_fk = $bt_table_name.id"
      );
      if (array_key_exists($hm_alias, $assocs)) $params['load'] = $assocs[$hmt_alias];
      $hm_objs = eval("return $bt_klass::find_all(\$params);");
      if (is_array($assocs) && array_key_exists($hm_alias, $assocs))
      {
        ActiveRecord::eager_load_associated($hm_klass, $hm_objs, $assocs[$hmt_alias]);
      }
      foreach($hm_objs as $assoc_obj)
      {
        foreach($objs as $k=>$v)
        {
          if ($v->id() == $assoc_obj->__rel_id)
          {
            array_push($v->$hmt_alias, $assoc_obj);
          }
        }
        unset($assoc_obj->__rel_id);
      }
  	}		
  }
  
  
  
  function __toString()
  {
    if($this->id()) return (string)$this->id();
    return spl_object_hash($this);
  }
  
  
  static function _model_table_name($klass)
  {
  	if ($klass=='activerecord') W::error("Recursion error on $klass");
  	$tn = eval("return $klass::\$table_name;");
  	return $tn;
  }
  
  
  function _format($k)
  {
    if($this->$k===null) return null;
  
    $klass = $this->klass;
	  $ms = static::$model_settings;
		if(!isset($ms['type'][$k])) return;
    list($type, $length) = $ms['type'][$k];
		switch($type)
		{
      case 'datetime':
      case 'timestamp':
      case 'date':
        if(!is_numeric($this->$k))
        {
          $old = date_default_timezone_get();
          date_default_timezone_set('UTC');
          $this->$k = str_replace('/', '-', $this->$k);
          if(method_exists('DateTime', 'createFromFormat'))
          {
            $formats = array(
              'Y-m-d H:i:s',
              'm-d-Y',
            );
            foreach($formats as $f)
            {
              $dt = DateTime::createFromFormat($f,$this->$k);
              if($dt)
              {
                $this->$k = $dt->getTimestamp();
                break;
              }
            }
          } else {
            $v = strtotime( $this->$k );
            $this->$k = $v;
          }
          date_default_timezone_set($old);
        }
        break;
      case 'smallint':
      case 'int':
      case 'tinyint':
      case 'bigint':
        $this->$k = ($this->$k==='') ? null : (int)$this->$k;
        break;
      case 'double':
      case 'float':
        $this->$k = ($this->$k==='') ? null : (double)$this->$k;
        break;
      case 'enum':
      case 'varchar':
      case 'longtext':
      case 'char':
      case 'tinytext':
        break;
      case 'decimal':
        list($integer, $fraction) = explode(',', $length);
        if ($fraction==0)
        {
          $this->$k = ($this->$k==='') ? null : (int)$this->$k;
        } else {
          $this->$k = ($this->$k==='') ? null : (double)$this->$k;
        }
        break;
      default:
        W::error("Unsupported type for $k: " . $type, $this);
    }
  }
   
  
  function update_attributes($arr, $save=true)
  {
  	$this->event('before_update_attributes', array('params'=>$arr));

  	foreach($arr as $k=>$v)
  	{
  		$this->$k = $v;
  		$ov = "{$k}_original_value";
  		if (!isset($this->$ov)) $this->$ov = $v;
  	}
  	
  	$this->event('update_attributes', array('params'=>$arr));
  	
    foreach($arr as $k=>$v)
    {
  		$this->_format($k);
    }

  	if ($save)
  	{
      if($this->validate())
      {
        $this->save();
      }
  	}
  	$this->event('after_update_attributes', array('params'=>$arr));
  }
  
  function validate()
  {


  	$this->errors=array();
  	$klass=$this->klass;
  	
    $this->event('before_validate');
  	
  	// Validate presence
  	$fields = eval("return $klass::\$validates_presence_of;");
	  $ms = static::$model_settings;
  	foreach($ms['is_nullable'] as $k=>$v)
  	{
  		if ( (!$v) && array_search($k, array('id', 'created_at', 'updated_at'))===FALSE)
  		{
  			$fields[] = $k;
  		}
  	}
  	foreach($fields as $field)
  	{
    	if (is_object($this->$field)|| is_array($this->$field)) continue;
    	if (array_key_exists($field, $this->errors)) continue;
  		if ($this->$field === null || trim($this->$field) == '')
  		{
  			$this->errors[$field] = 'is required.';
  		}
  	}
  	
  	// validate maxlengths
  	$fields = eval("return $klass::\$validates_length_of;");
  	if (array_key_exists("max_length", $ms))
  	{
      $fields = array_merge($ms['max_length'], $fields);
    }
  	foreach($fields as $field=>$max_length)
  	{
    	if (is_object($this->$field)|| is_array($this->$field)) continue;
    	if (array_key_exists($field, $this->errors)) continue;
  		if (strlen($this->$field) > $max_length)
  		{
  			$this->errors[$field] = ' must be ' . $ms['max_length'][$field] . ' characters or less.';
  		}
  	}

  	// validate uniqueness
  	$field_sets = eval("return $klass::\$validates_uniqueness_of;");
  	foreach($field_sets as $field_set)
  	{
      $where = array('id <> ?');
      foreach($field_set as $field_name)
      {
        $where[] = "`{$field_name}` = ?";
      }
      $s = join(" and ", $where);
      $where = array($s, $this->id);
      foreach($field_set as $field_name)
      {
        $where[] = $this->$field_name;
      }
    	$params = array(
        'conditions'=>$where,
      );
    	$c = eval("return $klass::count(\$params);");
    	if ($c>0)
    	{
        if(count($field_set)>1)
        {
          $this->errors[join(':',$field_set)] = ' combo is already taken';
        } else {
          foreach($field_set as $field_name)
          {
      			$this->errors[$field_name] = ' is already taken';
      		}
        }
  		}
  	}
  	  		
  	
  	$this->is_valid = count($this->errors)==0;
  	
  	$this->event('validate');		
  	$this->is_valid = count($this->errors)==0;
  	
  	if($this->is_valid)
  	{
    	// validate format
    	$fields = eval("return $klass::\$validates_format_of;");
    	if (array_key_exists("format", $ms))
    	{
        $fields = array_merge($ms['format'], $fields);
      }
    	foreach($fields as $field=>$regex)
    	{
    	  if(preg_match($regex, $this->$field)==0)
    	  {
    			$this->errors[$field] = 'is an invalid format.';
    		}
    	}  
    }
  	$this->is_valid = count($this->errors)==0;
  	
  	
  	
  	$this->event('after_validate');
  	return $this->is_valid;
  }
  
  
  static function order_by()
  {
    return '';
  }
  
  function _after_load()
  {
    $this->event('unserialize');
    
    $klass = $this->klass;
	  $an = static::$attribute_names;
    foreach($an as $k)
    {
      $ov = "{$k}_original_value";
      $this->$ov = $this->$k;
    }
  
  	$this->event('after_load');
  }
  
  
function filter_text($text)
{
  return trim(preg_replace("/\s+/", ' ', preg_replace("/[^A-Za-z0-9\$]/", " ", $text)));
}

/*
Your method should respond with:

<model>_index.php

That should return all the properties you want indexed, as follows:

$text = array(
  $model->prop1,
  $model->prop2,
  ...
);
*/
function index()
{
  if ($this->klass=='Search') return
  $this->event('before_index');   
  $event_data = $this->event('index'); 
  $text = array();
  foreach($event_data as $module_name=>$vars)
  {
    if (array_key_exists('text', $vars))
    {
      if(is_array($vars['text']))
      {
        $vars['text'] = join(' ', $vars['text']);
      }
      $text[] = $vars['text'];
    }
  }
  $text = join(' ', $text);
  $text = $this->filter_text($text);
  if (strlen($text)>0)
  {
    $o = Search::find( array(
      'conditions'=>array('model_name = ? and record_id = ?', $this->klass, $this->id())
    ));
    if ($o)
    {
      if (strlen($text)==0)
      {
        $o->delete();
      } else {
        $o->search_text = $text;
        $o->save();
      }
    } else {
      if (strlen($text)>0)
      {
        $o = Search::create( array(
          'attributes' => array(
            'record_id'=>$this->id(),
            'model_name'=>$this->klass,
            'search_text'=>$text
          )
        ));
      }
    }
  }
  $this->event('after_index');
}


  function save_as_new()
  {
    return $this->save(true);
  }
  
  function save($create_new = false)
  {
    global $event_table;
    
  	if (!$this->validate()) return false;
    $this->event('serialize');
    
    
    // valiate db formats
  	$klass=$this->klass;
	  $ms = static::$model_settings;
	  $an = static::$attribute_names;
    foreach($an as $field)
  	{
      // post-serialize
   	  if($this->$field === false) $this->$field = 0;

      // validation checking
      if (is_object($this->$field)|| is_array($this->$field)) 
      {
        W::error("$field is an object or array. Did you forget to serialize()?", array($this, $event_table)); 
      }
      if (array_key_exists('db_format', $ms) && array_key_exists($field, $ms['db_format']))
      {
      	if (preg_match($ms['db_format'][$field], $this->$field)==0)
      	{
          W::error("{$klass}->{$field} is not of the format {$ms['db_format'][$field]}. Failed to serialize properly.", array($this, $event_table));
      	}
      }
    }

		$this->event('before_save');
		if (!$this->is_new && !$create_new)
		{
		  $this->update();
		} else {
		  $this->insert();
		}
		$this->event('after_save');
  	$this->event('unserialize');

  	return true;
  }
  
  static function db_date($when)
  {
    if($when=="") return 'null';
    $old = date_default_timezone_get();
    date_default_timezone_set('UTC');
    $dt = date( 'Y-m-d H:i:s e', $when );
    date_default_timezone_set($old);
    return $dt;
  }
  
  function insert()
  {
  	$this->event('before_insert');

  	$klass=$this->klass;
	  $ms = static::$model_settings;
  	if ($ms['is_auto_increment']['id']) $attrs = $this->attributes_except('id'); else $attrs = $this->attributes();
  	if (array_key_exists('created_at', $attrs))
  	{
      $this->created_at = time();
      $attrs['created_at'] = $this->created_at;
    }
  	if (array_key_exists('updated_at', $attrs))
  	{
  	 $this->updated_at = time();
  	 $attrs['updated_at'] = $this->updated_at;
    }
  	foreach($attrs as $k=>$v) if ($ms['type'][$k][0] == 'timestamp') unset($attrs[$k]);
  	$fields = "`" . join(array_keys($attrs),'`,`') . "`";
  	$values = array();
  	$this->is_new=false;
  
  	foreach($attrs as $k=>$v)
  	{
  		if ($v===null)
  		{
  			$values[] = 'null';
  		} else {
  		  switch($ms['type'][$k][0])
  		  {
  		    case 'datetime':
  		      $v = ActiveRecord::db_date($v);
  		      break;
          case 'varchar':
          case 'longtext':
          case 'char':
            $v = preg_replace("/\r/", '', $v);
            break;
          case 'int':
          case 'tinyint':
          case 'bigint':
          case 'float':
          case 'decimal':
            if ($v===false) $v=0;
            if ($v==='') $v=null;
            break;
          case 'varchar':
          case 'varchar':
          case 'longtext':
          case 'char':
            if ($v===false) $v='';
            break;  		  
  		  }
        if($v!==null)
        {
    			$values[] = ActiveRecord::quote($v);
    		} else {
    		  $values[] = 'null';
    		}  
  		}
  	}
  	$values = join($values,", ");
  	$tn = ActiveRecord::_model_table_name($klass);
  	$sql = "insert into $tn ($fields) values ($values)";
  	$this->last_query = $sql;
  	W::db_query($sql);
  	if ($ms['is_auto_increment']['id']) 
  	{
      global $__wicked;
  	  $this->id(mysql_insert_id($__wicked['modules']['db']['current']['handle']));
  	} else {
  		if ($attrs[$this->pk()])
  		{
  		  $id = $attrs[$this->pk()];
  		  $this->id($id);
  		}
  	}
  	$this->event('after_insert');
  	return true;
  }
  
  static function sanitize($s)
  {
  	return mysql_real_escape_string($s);
  }
  
  static function quote($v)
  {
  	return "'" . ActiveRecord::sanitize($v) . "'";
  }
  
  function update()
  {
  	$klass=$this->klass;
	  $ms = static::$model_settings;
  	if ($ms['is_auto_increment']['id']) $attrs = $this->attributes_except('id'); else $attrs = $this->attributes();
  	if (array_key_exists('updated_at', $attrs))
  	{
      $this->updated_at = time();
      $attrs['updated_at'] = $this->updated_at;
    }

  	foreach($attrs as $k=>$v) if ($ms['type'][$k][0] == 'timestamp') unset($attrs[$k]);
  	$assignments = array();
  
  	foreach($attrs as $k=>$v)
  	{
  		if ($v===null)
  		{
  			$assignments[] = "`$k`=null";
  		} else {
  		  switch($ms['type'][$k][0])
  		  {
  		    case 'datetime':
  		      $v = ActiveRecord::db_date($v);
  		      break;
          case 'varchar':
          case 'longtext':
          case 'char':
            $v = preg_replace("/\r/", '', $v);
            break;
          case 'int':
          case 'tinyint':
          case 'bigint':
          case 'float':
          case 'decimal':
            if ($v===false) $v=0;
            if ($v==='') $v=null;
            break;
          case 'varchar':
          case 'varchar':
          case 'longtext':
          case 'char':
            if ($v===false) $v='';
            break;  		  
        }
        if($v!==null)
        {
    			$assignments[] = "`$k` = " . ActiveRecord::quote($v);
    		} else {
    			$assignments[] = "`$k` = null";
    		}  
  		}
  	}
  	$assignments=join($assignments,', ');
  	$tn = ActiveRecord::_model_table_name($klass);
  	$sql = "update $tn set $assignments where {$this->pk()}='{$this->id()}'";
  	$this->last_query = $sql;
  	W::db_query($sql);
  	return true;
  }
  
  function attributes()
  {
  	$attr = array();
  	$an = static::$attribute_names;
  	foreach($an as $k)
  	{
  		$attr[$k] = $this->$k;
  	}
  	return $attr;
  }
  
  function attributes_except()
  {
  	$a = $this->attributes();
      for ($i = 0;$i < func_num_args();$i++)
      {
      	$name = func_get_arg($i);
      	unset($a[$name]);
      }
      return $a;
  }
  
  function collection_contains($haystack,$needle)
  {
  	foreach($haystack as $obj)
  	{
  		if ($obj->id()==$needle->id()) return true;
  	}
  	return false;
  }
  
  

  
  // Lazy-load associations. PHP4 overloading is too buggy to use.
  function a($assocs)
  {
    if (!is_array($assocs)) $assocs = array($assocs);
    $objs = array($this);
    ActiveRecord::eager_load_associated(get_class($this), $objs, $assocs);
  }
    
  function collection_class_name($coll)
  {
    foreach($this->$has_many as $k=>$v)
    {
      if (is_numeric($k))
      {
        if ($coll == $v)
        {
          return W::classify(W::singularize($v));
        }
      } else {
        if ($coll == $v)
        {
          return W::classify(W::singularize($k));
        }
      }
    }
  }
  
  function sort($coll_name, $field_name, $order = 'asc')
  {
    $this->sort_field_name = $field_name;
    $this->sort_order = $order;
    $this->$coll_name;
    usort($this->$coll_name,  array(&$this, "compare_models"));
    return $this->$coll_name;
  }
  
  function compare_models($a, $b)
  {
    $a_val = eval("return \$a->$this->sort_field_name;");
    $b_val = eval("return \$b->$this->sort_field_name;");
    if ($a_val==$b_val) return 0;
    if ($a_val < $b_val) return -1;
    return 1;
  }
  
  static function add_condition($params, $where)
  {
    if (array_key_exists('conditions', $params))
    {
      if (!is_array($params['conditions']))
      {
        $params['conditions'] = array($params['conditions']);
      }
      $params['conditions'][0] .= " and ";
    } else {
      $params['conditions'] = array('');
    }
    $params['conditions'][0] .= $where;
    if(func_num_args()>2)
    {
      for($i=2;$i<func_num_args();$i++)
      {
        $params['conditions'][] = func_get_arg($i);
      }
    }
    return $params;
  }

  static function add_conditions($params, $conditions)
  {
    if (array_key_exists('conditions', $params))
    {
      if (!is_array($params['conditions']))
      {
        $params['conditions'] = array($params['conditions']);
      }
      $params['conditions'][0] .= " and ";
    } else {
      $params['conditions'] = array('');
    }
    $params['conditions'][0] .= array_shift($conditions);
    $params['conditions'] = array_merge($params['conditions'], $conditions);
    return $params;
  }

  function purge($name)
  {
    foreach($this as $k=>$v)
    {
      if (startswith($k, "__cached__{$name}")) unset($this->$k);
    }
    unset($this->$name);
  }
  
  function copy()
  {
    $names = static::$attribute_names;
    $o = new $this->klass();
    foreach($names as $n)
    {
      if($n=='id') continue;
      $o->$n = $this->$n;
    }
    $o->save();
    return $o;
  }

  static function add_post_filter($params, $name)
  {
    if(!isset($params['post_filters'])) $params['post_filters'] = array();
    $params['post_filters'][] = $name;
    return $params;
  }
  
  static function _stub_params($val, $params, $table_name, $field_name)
  {
    if($val===null)
    {
      $params = ActiveRecord::add_condition($params, "`$table_name`.`$field_name` is null");
    } else {
      $params = ActiveRecord::add_condition($params, "`$table_name`.`$field_name` = ?", $val);
    }
    return $params;
  }
}
