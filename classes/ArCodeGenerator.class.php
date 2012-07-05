<?

class ArCodeGenerator
{
  function __construct(&$config, $base_fpath, $cache_fpath)
  {
    $this->config = &$config;
    $this->model_info = array();
    $this->attribute_names = array();
    $this->codegen = array(
      'models'=>array(),
    );
    $this->base_fpath = $base_fpath;
    $this->cache_fpath = $cache_fpath;
  }
  
  function compute_model_settings($klass, $tn)
  {
  	$arr = query_assoc("desc $tn");
  	$attr=array();
  	foreach($arr as $rec)
  	{
  		$attr[] = $rec['Field'];
  
   		$parts = preg_split("/[\\(\\)]/", $rec['Type']);
  		$typeinfo[0] = $parts[0];
  		$typeinfo[1] = null;
  		if (count($parts)>1) $typeinfo[1] = $parts[1];
 
      if($rec['Key']=="PRI") $this->model_info[$klass]['pk'] = $rec['Field'];
  		$this->model_info[$klass]['type'][$rec['Field']] = $typeinfo;
  		$this->model_info[$klass]['is_nullable'][$rec['Field']] = $rec['Null']=='YES' || in($rec['Field'], 'id', 'created_at', 'updated_at');
  		$this->model_info[$klass]['is_auto_increment'][$rec['Field']] = ($rec['Extra'] == 'auto_increment');
  		$parts = preg_split("/[\\(\\)]/", $rec['Type']);
  		$type = array_shift($parts);
  		if (count($parts)>0) $length = array_shift($parts);
  		switch($type)
  		{
  			case 'enum':
          $length = preg_replace("/'/", '', $length);
          $values = explode(',',$length);
          $regex = array();
          $length = 0;
          foreach($values as $v)
          {
            $length = max($length, strlen($v));
            $regex[] = preg_quote($v);
          }
          $regex = join('|',$regex);
  				$this->model_info[$klass]['max_length'][$rec['Field']] = $length;
  				$this->model_info[$klass]['db_format'][$rec['Field']] = '/'.$regex.'/';
  				$this->model_info[$klass]['format'][$rec['Field']] = '/'.$regex.'/';
  				break;
  			case 'varchar':
  				$this->model_info[$klass]['max_length'][$rec['Field']] = $length;
  				break;
      		case 'char':
      			$this->model_info[$klass]['max_length'][$rec['Field']] = $length;
      			break;
  			case 'int':
  				$this->model_info[$klass]['min_value'][$rec['Field']] = -2147483648;
  				$this->model_info[$klass]['max_value'][$rec['Field']] = 2147483647;
  				$regex = '^\s*-?(\d+)\s*$';
  				if ($this->model_info[$klass]['is_auto_increment'][$rec['Field']] || $this->model_info[$klass]['is_nullable'][$rec['Field']]) $regex = '(?:^\s*$)|'.$regex;
  				$this->model_info[$klass]['db_format'][$rec['Field']] = '/'.$regex.'/';
  				$this->model_info[$klass]['format'][$rec['Field']] = '/'.$regex.'/';
  				break;
    		case 'bigint':
    			$this->model_info[$klass]['min_value'][$rec['Field']] = -9223372036854775808;
    			$this->model_info[$klass]['max_value'][$rec['Field']] = 9223372036854775807;
  				$regex = '^\s*(\d+)\s*$';
  				if ($this->model_info[$klass]['is_auto_increment'][$rec['Field']] || $this->model_info[$klass]['is_nullable'][$rec['Field']]) $regex = '(?:^\s*$)|'.$regex;
  				$this->model_info[$klass]['db_format'][$rec['Field']] = '/'.$regex.'/';
  				$this->model_info[$klass]['format'][$rec['Field']] = '/'.$regex.'/';
    			break;
  			case 'tinyint':
  				$this->model_info[$klass]['min_value'][$rec['Field']] = -128;
  				$this->model_info[$klass]['max_value'][$rec['Field']] = 127;
  				$regex = '^\s*(\d+)\s*$';
  				if ($this->model_info[$klass]['is_auto_increment'][$rec['Field']] || $this->model_info[$klass]['is_nullable'][$rec['Field']]) $regex = '(?:^\s*$)|'.$regex;
  				$this->model_info[$klass]['db_format'][$rec['Field']] = '/'.$regex.'/';
  				$this->model_info[$klass]['format'][$rec['Field']] = '/'.$regex.'/';
  				break;
  			case 'smallint':
  				$this->model_info[$klass]['min_value'][$rec['Field']] = -32768;
  				$this->model_info[$klass]['max_value'][$rec['Field']] = 32767;
  				$regex = '^\s*(\d+)\s*$';
  				if ($this->model_info[$klass]['is_auto_increment'][$rec['Field']] || $this->model_info[$klass]['is_nullable'][$rec['Field']]) $regex = '(?:^\s*$)|'.$regex;
  				$this->model_info[$klass]['db_format'][$rec['Field']] = '/'.$regex.'/';
  				$this->model_info[$klass]['format'][$rec['Field']] = '/'.$regex.'/';
  				break;
  			case 'bool':
  				$this->model_info[$klass]['value_set'][$rec['Field']] = array(0,1);
  				break;
  		  case 'tinytext':
          $model_settiongs[$klass]['max_length'][$rec['Field']] = pow(2,8)-1;
          break;
  		  case 'text':
          $model_settiongs[$klass]['max_length'][$rec['Field']] = pow(2,16)-1;
          break;
  		  case 'mediumtext':
          $model_settiongs[$klass]['max_length'][$rec['Field']] = pow(2,32)-1;
          break;
        case 'longtext':
          $model_settiongs[$klass]['max_length'][$rec['Field']] = pow(2,64)-1;
          break;
        case 'time':
        case 'date':
        case 'datetime':
  				$regex = '^\s*-?(\d+)\s*$';
  				if ($this->model_info[$klass]['is_auto_increment'][$rec['Field']] || $this->model_info[$klass]['is_nullable'][$rec['Field']]) $regex = '(?:^\s*$)|'.$regex;
  				$this->model_info[$klass]['db_format'][$rec['Field']] = '/'.$regex.'/';
  				$this->model_info[$klass]['format'][$rec['Field']] = '/'.$regex.'/';
          break;
        case 'timestamp':
  				$regex = '^\s*(\d+)\s*$';
  				if ($this->model_info[$klass]['is_auto_increment'][$rec['Field']] || $this->model_info[$klass]['is_nullable'][$rec['Field']]) $regex = '(?:^\s*$)|'.$regex;
  				$this->model_info[$klass]['db_format'][$rec['Field']] = '/'.$regex.'/';
  				$this->model_info[$klass]['format'][$rec['Field']] = '/'.$regex.'/';
          break;
        case 'double':
        case 'float':
  				$this->model_info[$klass]['min_value'][$rec['Field']] = -99999.0;
  				$this->model_info[$klass]['max_value'][$rec['Field']] = 99999.0;
  				$regex = '^-?[0-9]*\.?[0-9]+$';
  				if ($this->model_info[$klass]['is_auto_increment'][$rec['Field']] || $this->model_info[$klass]['is_nullable'][$rec['Field']]) $regex = '(?:^\s*$)|'.$regex;
  				$this->model_info[$klass]['db_format'][$rec['Field']] = '/'.$regex.'/';
  				$this->model_info[$klass]['format'][$rec['Field']] = '/'.$regex.'/';
  				break;
  			case 'decimal':
  				$regex = '^[-+]?[0-9]*\.?[0-9]+$';
  				if ($this->model_info[$klass]['is_auto_increment'][$rec['Field']] || $this->model_info[$klass]['is_nullable'][$rec['Field']]) $regex = '(?:^\s*$)|'.$regex;
  				$this->model_info[$klass]['db_format'][$rec['Field']] = '/'.$regex.'/';
  				$this->model_info[$klass]['max_length'][$rec['Field']] = 10;
  				$this->model_info[$klass]['format'][$rec['Field']] = '/'.$regex.'/';
  				break;
  		  case 'blob':
  		    break;
  		  default:
  		    wicked_error("Unsupported data type {$rec['Type']}", array($klass, $rec));
  		    break;
  		}
  		$this->model_info[$klass]['default_value'][$rec['Field']] = $rec['Default'];
  	}
  	$this->attribute_names[$klass] = $attr;
  	return $attr;
  }
  
  
  function find_belongs_tos($tables)
  {
    $belongs_to = array();
    foreach($tables as $table_name=>$fields)
    {
  		$belongs_to[$table_name] = array();
  		foreach($fields as $data)
  		{
  			$field_name = $data['Field'];
  			if (endswith($field_name, '_id'))
  			{
  			  $bt_alias = startof($field_name,'_id');
  			  $bt_class_name = $this->config['class_prefix'].classify($bt_alias);
  			  if ($data['Comment']!='')
  			  {
  			    $bt_class_name = $this->config['class_prefix'].classify(singularize(trim($data['Comment'])));
  			  }
  			  if($bt_class_name == '-') continue; 
  				$belongs_to[$table_name][$bt_alias] = array($bt_class_name, $field_name);
  			}
  		}
    }
    return $belongs_to;
  }
  
  function find_has_manys($tables)
  {
    $has_many = array();
    foreach($tables as $table_name=>$fields)
    {
      $stn = singularize($table_name);
  		$has_many[$table_name] = array();
  		$hm_duplicates = array();
  		foreach($tables as $hm_table_name=>$hm_fields)
  		{
  			foreach($hm_fields as $data)
  			{
  		    if($data['Comment']=='-') continue; // force skip
  				$field_name = $data['Field'];
   			  if ($data['Comment']!='') $field_name = strtolower(singularize(tableize($data['Comment']))) .'_id';
  				if ($field_name != $stn.'_id') continue; // skip if it's not referring back to the master table
  			  if (isset($hm_duplicates[$hm_table_name]))
  			  {
  			    if($hm_duplicates[$hm_table_name]===false) // duplicate found, but no fixup yet
  			    {
  			     $new_alias = $hm_table_name . '_by_' . $has_many[$table_name][$hm_table_name][1];
  			     $has_many[$table_name][$new_alias] = $has_many[$table_name][$hm_table_name];
  			     unset($has_many[$table_name][$hm_table_alias]);
  			     $hm_duplicates[$hm_table_name] = true;
  			    }
  			    $hm_table_alias = $hm_table_name . '_by_' . $data['Field'];
  			  } else {
    			  $hm_duplicates[$hm_table_name] = false; // not duplicated yet
    			  $hm_table_alias = $hm_table_name;
  			  }
  			  $hm_klass_name = $this->config['class_prefix'].classify(singularize($hm_table_name));
  				$has_many[$table_name][$hm_table_alias] = array($hm_klass_name, $data['Field'], $hm_table_name);
  			}
  		}
    }
    return $has_many;
  }
  
  function find_has_many_throughs($has_many, $belongs_to)
  {
    $has_many_through = array();
    foreach($has_many as $table_name=>$hm_data)
    {
      $has_many_through[$table_name] = array();
      foreach($hm_data as $hm_name=>$hm_info)
      {
        $btn = $hm_info[2];
        foreach($belongs_to[$btn] as $bt_name=>$bt_info)
        {
          if($bt_info[1]==$hm_info[1]) continue;
          $hmt_name = pluralize($bt_name);
          if(array_key_exists($hmt_name, $has_many[$table_name])) $hmt_name = "{$hmt_name}_through_{$hm_name}";
          $has_many_through[$table_name][$hmt_name] = array($hm_name, $bt_name);
        }
      }
    }
    return $has_many_through;
  }
  
  
  function should_codegen_table($table_name)
  {
    foreach($this->config['tables']['exclude'] as $e)
    {
      if(preg_match("/{$this->config['prefix']}{$e}/", $table_name)) return false;
    }
    foreach($this->config['tables']['include'] as $e)
    {
      if(preg_match("/{$this->config['prefix']}{$e}/", $table_name)) return true;
    }
    return false;
  }
  
  function find_tables()
  {
    $tables = array();
    $recs = query_assoc("show tables");
    $this->config['models']=array();
    foreach($recs as $table)
    {
      foreach($table as $k=>$table_name)
      {
        if(!$this->should_codegen_table($table_name)) continue;
        $ar_table_name = $this->deprefix($table_name);
        $this->config['table_lookup'][$ar_table_name] = $table_name;
        $tables[$ar_table_name] = query_assoc("show full columns from $table_name");
        $stn = singularize($ar_table_name);
        $klass=classify($stn);
        $this->compute_model_settings($klass, $table_name);
        $this->config['models'][] = $klass;
        
      }
    }
    return $tables;
  }
  
  
  function find_attribute_types($tables, $belongs_to, $has_many, $has_many_through)
  {
    $attribute_types = array();
    foreach($tables as $table_name=>$fields)
    {
      $stn = singularize($table_name);
      $klass=classify($stn);
    
  		$attribute_types[$table_name] = array();
  
      foreach($this->model_info[$klass]['type'] as $k=>$column_info)
      {
        list($type,$length) = $column_info;
        if(!isset($this->config['type_mappings'][$type])) wicked_error("No type mapping '$type' for $table_name.$k");
        $v = array('type'=>$this->config['type_mappings'][$type] , 'required'=>!$this->model_info[$klass]['is_nullable'][$k], 'default'=>$this->model_info[$klass]['default_value'][$k]);
        if(isset($this->config['conventions'][$type]))
        {
          foreach($this->config['conventions'][$type] as $c_word=>$c_value)
          {
            if(has_word($k, $c_word))
            {
              $v['type'] = $c_value;
            }
          }
        }
        $attribute_types[$table_name][$k] = $v;
      }
      foreach($belongs_to[$table_name] as $k=>$info)
      {
        $attribute_types[$table_name][$info[1]]['type'] = 'select';
        $attribute_types[$table_name][$info[1]]['item_array'] = 'available_'.pluralize($k);
        $attribute_types[$table_name][$info[1]]['display_field']='name';
        $attribute_types[$table_name][$info[1]]['value_field']='id';
        $attribute_types[$table_name][$k]['type'] = 'select';
        $attribute_types[$table_name][$k]['item_array'] = 'available_'.pluralize($k);
        $attribute_types[$table_name][$k]['display_field']='name';
        $attribute_types[$table_name][$k]['value_field']='id';
      }
      
      foreach($has_many[$table_name] as $k=>$info)
      {
        $attribute_types[$table_name][$k] = array('type'=>'mutex', 'item_array'=>'available_'.$k, 'selected_item_array'=>$k, 'display_field'=>'name', 'value_field'=>'id', 'klass'=>singularize(classify($info[0])));
      }
      foreach($has_many_through[$table_name]  as $k=>$hmk)
      {
        $hm_table_name = $has_many[$table_name][$hmk[0]][2];
        $klass = $this->config['class_prefix'].singularize(classify($belongs_to[$hm_table_name][$hmk[1]][0])); // Have to look up the underlying table from the $belongs_to via the $has_many assoc name
        $attribute_types[$table_name][$k] = array('type'=>'mutex', 'item_array'=>'available_'.$k, 'selected_item_array'=>$k, 'display_field'=>'name', 'value_field'=>'id', 'klass'=>$klass);
      }
    }
    return $attribute_types;
  }
  
  function find_uniques($tables)
  {
    $uniques = array();
    foreach($tables as $table_name=>$fields)
    {
      $res = query_assoc("show index from `!`", $table_name);
      $keys = array();
      foreach($res as $r)
      {
        if($r['Non_unique']) continue;
        $k = $r['Key_name'];
        if(!isset($keys[$k])) $keys[$k] = array();
        $keys[$k][] = $r['Column_name'];
      }
      $uniques[$table_name] = array_values($keys);
    }
    return $uniques;
  }
  
  function deprefix($s)
  {
    foreach($this->config['tables']['include'] as $e)
    {
      if(preg_match("/{$this->config['prefix']}{$e}/", $s, $matches))
      {
        if(count($matches)==1) wicked_error("No table name captured in /$e/");
        return $matches[1];
      }
    }
    return $s;
  }
  
  
  function codegen_models()
  {
    $tables = $this->find_tables();

    $belongs_to = $this->find_belongs_tos($tables);
    $has_many = $this->find_has_manys($tables);
    $has_many_through = $this->find_has_many_throughs($has_many, $belongs_to);
    $attribute_types = $this->find_attribute_types($tables, $belongs_to, $has_many, $has_many_through);
    $uniques = $this->find_uniques($tables);
    
    foreach($tables as $table_name=>$fields)
    {
      $stn = singularize($table_name);
      $klass=classify($this->deprefix($stn));
    
  		$s_belongs_to = s_var_export($belongs_to[$table_name]);
  		$s_has_many = s_var_export($has_many[$table_name]);
  		$s_hmt = s_var_export($has_many_through[$table_name] );
      $s_attribute_types = s_var_export($attribute_types[$table_name]);
      $s_uniques = s_var_export($uniques[$table_name]); 
    
      $php = "<?\n".eval_php($this->base_fpath."/codegen/class_stub.php", 
  		  array(
  		    'klass'=>"{$this->config['class_prefix']}{$klass}",
  		    's_belongs_to'=>$s_belongs_to,
  		    's_has_many'=>$s_has_many,
  		    's_hmt'=>$s_hmt,
  		    's_attribute_types'=>$s_attribute_types,
  		    'fields'=>$fields,
  		    'table_name'=>$this->config['table_lookup'][$table_name],
  		    'stn'=>$stn,
  		    's_uniques'=>$s_uniques,
  		    's_model_settings'=>s_var_export($this->model_info[$klass]),
  		    's_attribute_names'=>s_var_export($this->attribute_names[$klass]),
  		  ),
  		  true
  		);
  		
      $fpath = $this->cache_fpath."/{$this->config['class_prefix']}{$klass}.class.php";
      file_put_contents($fpath, $php);
  	}
  }
  
  
  function codegen_model_extension($stn)
  {
    global $__wax;
  
    $php = '';
    foreach($__wax['manifests'] as $plugin_name=>$manifests)
    {
      foreach($manifests as $module_name=>$manifest)
      {
        $this_module_fpath = $manifest['path'];
        $lib_path = "$this_module_fpath/models/$stn.php";
        if (!file_exists($lib_path)) continue;
        $php .= "require('$lib_path');\n";
      }
    }
    return $php;
  }  
  
  function generate()
  {
    $this->codegen_models();
  }

  function calc_hash()
  {
    global $__wicked;
    
    if($this->config['always_generate']) return microtime(true);

    $keys = array();
    $recs = query_assoc("show tables");
    $tables = collect($recs, "Tables_in_".$__wicked['modules']['db']['current']['credentials']['catalog']);
    foreach($tables as $k)
    {
      if(!$this->should_codegen_table($k)) continue;
      $recs = query_assoc("show full columns from `$k`");
      $cols = array("Field", "Type", "Null", "Key", "Default", "Extra", "Comment");
      $digest = array();
      for($i=0;$i<count($recs);$i++)
      {
        $digest[$i] = $k.":";
        foreach($cols as $col)
        {
          $digest[$i] .= ":".$recs[$i][$col];
        }
      }
      $digest = md5(join('|',$digest));
      $keys[] = $digest;
    }
    
    $keys[] = md5_file($this->base_fpath."/codegen/class_stub.php");
    $keys[] = md5_file($this->base_fpath."/codegen.php");
    sort($keys);

    $md5 = md5(join('|',$keys));
    
    return $md5;
  }  
}