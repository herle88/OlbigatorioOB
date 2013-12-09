<?
class Actividades
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
			'titulo' => 'Nuestras Actividades',
			'descricpcion' => 'El complejo Cabañas Uruguay realiza varias actividades al aire libre, las cuales estan listadas a continuacion.',
			'items' => array()
		);
		
		$res = $this->site->datos->getActividades();
		
		while($reg = mysql_fetch_object($res)){
			$datos['items'][] = array(
				'titulo' => $reg->titulo,
				'descripcion' => substr(nl2br($reg->descripcion), 0, 200),
				'imagen' => $reg->imagen,
				'link' => SITE_ROOT.'actividades/'.$reg->id.'-'.$reg->titulo
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
		
		$aventura = $this->site->datos->getActividad($url[0]);
		
		if(!isset($aventura->id))
		{
			$this->site->throw404();
			return false;
		}
		
		$datos = array(
			'titulo' => $aventura->titulo,
			'descripcion' => nl2br($aventura->descripcion),
			'imagen' => $aventura->imagen
		);
		
		echo $parser->parseContent('detalles', $datos);
	}
}
?>