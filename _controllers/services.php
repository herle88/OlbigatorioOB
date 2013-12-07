<?
class Services
{
	function __construct ()
	{
		
	}
	
	function index ( $methods = array() )
	{
		echo 'hola indexxxxx';
	}
	
	function bus ($coso) 
	{
		echo 'hola busss ';
		print_r($coso);
	}
	
	function remap ( $method, $params = array() )
	{
		
	}
}
?>