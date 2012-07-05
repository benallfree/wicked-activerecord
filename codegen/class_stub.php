class <?=$klass?> extends ActiveRecord
{
  static $has_many = <?=$s_has_many?>;
  static $belongs_to = <?=$s_belongs_to?>;
  static $has_many_through = <?=$s_hmt?>;
  static $eager_load = array();
  static $validates_presence_of = array();
  static $validates_length_of = array();
  static $validates_uniqueness_of = <?=$s_uniques?>;
  static $validates_format_of = array();
  static $table_name = '<?=$table_name?>';
  static $attribute_types = <?=$s_attribute_types?>;
  static $attribute_names = <?=$s_attribute_names?>;
  static $model_settings = <?=$s_model_settings?>;
  public static $extend;
  var $klass = '<?=$klass?>';
  var $tableized_klass = '<?=$stn?>';

  static function count($params=array())
  {
    return ActiveRecord::_count('<?=$klass?>', $params);
  }
 
  static function create($params=array())
  {
    return ActiveRecord::_create('<?=$klass?>', $params);
  }

  static function bulk_create($params_arr=array())
  {
    return ActiveRecord::_bulk_create('<?=$klass?>', $params_arr);
  }

  static function delete_all($params=array())
  {
    return ActiveRecord::_delete_all('<?=$klass?>', $params);
  }
  
  static function find($params=array())
  {
    return ActiveRecord::_find('<?=$klass?>', $params);
  }
  

  static function find_all($params=array())
  {
    return ActiveRecord::_find_all('<?=$klass?>', $params);
  }

  static function find_or_create_by($params=array())
  {
    return ActiveRecord::_find_or_create_by('<?=$klass?>', $params);
  }

  static function create_or_update_by($params=array())
  {
    return ActiveRecord::_create_or_update_by('<?=$klass?>', $params);
  }


  static function find_or_new_by($params=array())
  {
    return ActiveRecord::_find_or_new_by('<?=$klass?>', $params);
  }
    
  static function select_assoc($params)
  {
    return ActiveRecord::_select_assoc('<?=$klass?>', $params);
  }

  <? foreach($fields as $data) {
      $field_name = $data['Field'];
  ?>
      
  static function sort_by_<?=$field_name?>(&$objs)
  {
    sort_by('<?=$field_name?>', $objs);
  }
  
  static function find_by_<?=$field_name?>($val, $params=array())
  {
    $params = ActiveRecord::_stub_params($val,$params, '<?=$table_name?>', '<?=$field_name?>');
    return ActiveRecord::_find('<?=$klass?>', $params);
  }


  static function find_all_by_<?=$field_name?>($val, $params=array())
  {
    $params = ActiveRecord::_stub_params($val,$params, '<?=$table_name?>', '<?=$field_name?>');
    return ActiveRecord::_find_all('<?=$klass?>', $params);
  }
  
  static function find_or_create_by_<?=$field_name?>($val, $params=array())
  {
    $params = ActiveRecord::_stub_params($val,$params, '<?=$table_name?>', '<?=$field_name?>');
    $params['attributes']['<?=$field_name?>'] = $val;
    return ActiveRecord::_find_or_create_by('<?=$klass?>', $params);
  }

  static function find_or_new_by_<?=$field_name?>($val, $params=array())
  {
    $params = ActiveRecord::_stub_params($val,$params, '<?=$table_name?>', '<?=$field_name?>');
    $params['attributes']['<?=$field_name?>'] = $val;
    return ActiveRecord::_find_or_new_by('<?=$klass?>', $params);
  } 
  <? } ?>
}
<?=$klass?>::$extend = (object)array();