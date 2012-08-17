<?

W::lazyload_add_path($config['cache_fpath']);

if($config['should_codegen'])
{
  require('codegen.php');
}
require($config['cache_fpath']."/config.php");
