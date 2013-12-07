<?
//Core
include(dirname(__FILE__).'/_config/constants.php');
include(dirname(__FILE__).'/_config/database.php');
include(dirname(__FILE__).'/_config/autoload.php');

//Classes
include(dirname(__FILE__).'/_class/class.CoreObligatorioOB.php');
$instance = new CoreObligatorioOB();

function get_instance()
{
	return $GLOBALS['instance'];
}




?>