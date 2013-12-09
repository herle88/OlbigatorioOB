<?
define('ROOT', dirname(__FILE__).'/');
//Core
include(dirname(__FILE__).'/_config/constants.php');
include(dirname(__FILE__).'/_config/database.php');
include(dirname(__FILE__).'/_config/autoload.php');
include(dirname(__FILE__).'/_config/menues.php');

$GLOBALS['template_data'] = array(
	'titulo' => 'Camping Uruguay',
	'base_url' => SITE_ROOT,
	'menues' => $menues
);

//Classes
include(dirname(__FILE__).'/_class/class.CoreObligatorioOB.php');
$instance = new CoreObligatorioOB();

function get_instance()
{
	return $GLOBALS['instance'];
}




?>