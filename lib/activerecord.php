<?
function get_user_prop($className, $property) {
  if(!class_exists($className)) return null;
  if(!property_exists($className, $property)) return null;

  $vars = get_class_vars($className);
  return $vars[$property];
}