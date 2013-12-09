<?
class Cabanias
{
	var $site;
	function __construct ()
	{
		$this->site = get_instance();
	}
	
	function index ( $methods = array() )
	{
		$this->site->loadLibrary('TemplateParser');
		$this->site->loadModel('Datos', 'datos');
		$parser = new TemplateParser();
		
		$datos = array(
			'titulo' => 'Nuestras Cabanias',
			'descripcion' => 'El complejo Cabañas Uruguay consta de: 9 cabañas, 4 bungalows equipados con todas las comodidades hasta 5 personas. En un entorno tranquilo y arbolado.<br>
Ubicación: Por algun logar de Canelones. 
A solo 3 cuadras de una de las mejores playas de Canelones y 5 cuadras del centro turístico y comercial ',
			'items' => array()
		);
		
		$res = $this->site->datos->getCabanias();
		
		while($reg = mysql_fetch_object($res)){
			$datos['items'][] = array(
				'titulo' => $reg->titulo,
				'descripcion' => substr(nl2br($reg->descripcion), 0, 200),
				'imagen' => $reg->imagen,
				'link' => SITE_ROOT.'cabanias/'.$reg->id.'-'.strtolower(str_replace(' ', '-', $reg->titulo))
			);
		}
		
		echo $parser->parseContent('listado', $datos);
	}
	
	function remap ( $method, $params = array() )
	{
		$this->site->loadLibrary('TemplateParser');
		$this->site->loadModel('Datos', 'datos');
		$parser = new TemplateParser();
		
		$url = explode('-', $method);
		
		if(sizeof($url) <= 1)
		{
			$this->site->throw404();
			return false;
		}
		
		$cabania = $this->site->datos->getCabania($url[0]);
		
		if(!isset($cabania->id))
		{
			$this->site->throw404();
			return false;
		}
		
		$datos = array(
			'titulo' => $cabania->titulo,
			'descripcion' => nl2br($cabania->descripcion),
			'imagen' => $cabania->imagen
		);
		
		echo $parser->parseContent('detalles', $datos);
	}
}
?>