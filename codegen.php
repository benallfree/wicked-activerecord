<?

W::load('string');
W::load('inflection');
W::load('path_utils');
W::load('exec');
W::load('debug');

$res = W::db_query_assoc("show variables where variable_name = 'ft_min_word_len'");
if ($res[0]['Value']> $config['ft_min_word_len']) wax_error("mySQL FullText searching error. Set ft_min_word_len >= {$config['ft_min_word_len']}. Currently set to: ". $res[0]['Value']);

$cg = new ArCodeGenerator($config, dirname(__FILE__), $config['cache_fpath']);
$md5 = $cg->calc_hash();
$hash_fpath = $config['cache_fpath']."/$md5";
if(!file_exists($hash_fpath))
{
  touch($hash_fpath);
  W::clear_cache($config['cache_fpath']);
  $cg->generate();
  
  $model_info = W::s_var_export($cg->model_info);
  $attribute_names = W::s_var_export($cg->attribute_names);
  $php = <<<PHP
  <?
  \$config['model_info'] = $model_info;
  \$config['attribute_names'] = $attribute_names;
PHP;

  file_put_contents($config['cache_fpath']."/config.php", $php);
}