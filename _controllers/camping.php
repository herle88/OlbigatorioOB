<?
class Camping 
{
	function index(){
		$site = get_instance();
		$site->loadLibrary('TemplateParser');
		
		$parser = new TemplateParser();
		
		//$site->loadModel('Datos', 'datos');
		
		echo $parser->parseContent('camping', array());
	}
}
?>