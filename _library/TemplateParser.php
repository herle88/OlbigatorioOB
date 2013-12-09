<?
class TemplateParser {
	var $default_struct = array(
		'header',
		'menu',
		'_content_',
		'footer'
	);
	var $default_data = array();
	
	private $site = null;
	private $parser = null;
	
	function __construct(){
		$this->site = get_instance();
		$this->site->loadLibrary('Mustache');
		$this->parser = new Mustache_Engine();

		$this->setDefaultData($GLOBALS['template_data']);
	}
	
	function parseContent ($template_name, $data){
		$parsed = '';
		$data = array_merge($this->default_data, $data);
		foreach($this->default_struct as $tpl){
			if($tpl != '_content_')
				$template = $this->getTemplate($tpl);
			else
				$template = $this->getTemplate($template_name);
			
			$parsed[] = $this->parser->render($template, $data);
		}
		return implode("\r\n", $parsed);
	}
	
	function getTemplate ($name){
		return file_get_contents(TEMPLATES_PATH.$name.TEMPLATES_SUFIX);
	}
	
	function setDefaultData(&$data){
		$this->default_data = $data;
	}
}
?>