<?
class Casas_Rodantes 
{
	function index(){
		$site = get_instance();
		$site->loadLibrary('TemplateParser');
		
		$parser = new TemplateParser();
		
		//$site->loadModel('Datos', 'datos');
		
		echo $parser->parseContent('casas_rodantes', array());
	}
}
?>