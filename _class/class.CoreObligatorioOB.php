<?
class CoreObligatorioOB
{
	function __construct ()
	{
		$GLOBALS['instance'] = $this;
		$this->connect_db();
		$this->includeDir( ROOT.'/_controllers/' );
		$this->parseURL();
	}
	
	function parseURL ()
	{
		$uri = str_replace( SITE_ROOT, '', $_SERVER['REQUEST_URI'] );
		$method_params = explode( '/', $uri );
		
		if( $method_params[ sizeof( $method_params ) - 1 ] == '' )
			array_pop( $method_params );
			
		if( sizeof( $method_params ) || DEFAULT_CONTROLLER != '')
		{
			if( !isset($method_params[0]) )
				$controller = DEFAULT_CONTROLLER;
			else
				$controller = ucfirst($method_params[0]);
			
			if( class_exists( $controller ) )
			{
				array_shift( $method_params ); //Me quedo con todo menos la contr
		
				$controllerInstance = new $controller();
				
				if( method_exists( $controllerInstance, 'index' ) && sizeof( $method_params ) === 0 )
				{
					$controllerInstance->index();
				}
				else if( isset($method_params[ 0 ]) && method_exists( $controllerInstance, $method_params[ 0 ] ) )
				{
					$method = array_shift( $method_params );
					$controllerInstance->{$method}( $method_params );
				}
				else if( method_exists( $controllerInstance, 'remap' ) )
				{
					$method = array_shift( $method_params );
					$controllerInstance->remap( $method, $method_params );
				}
			}
		}
		else
		{
			$controllerInstance = new DEFAULT_CONTROLLER();
		}
	}
	
	function loadModel ( $model_name, $local_instance = '' )
	{
		$path = MODEL_PATH.'/'.MODEL_PREFIX.$model_name.'.'.EXT;
		if( file_exists( $path ) )
		{
			require_once( $path );
			$class_name = ucfirst( $model_name );
			$instance = new $class_name();
			
			if( $instance != '' )
				$this->$local_instance = $instance;
			return $instance;
		}
		else
		{
			die( 'Imposible cargar modelo: '.$model_name );
		}
	}
	
	function loadLibrary ( $library_name )
	{
		$path = LIBRARY_PATH.'/'.$library_name.'.'.EXT;
		if( file_exists( $path ) )
			include( $path );
		else
			die( 'Imposible cargar libreria: '.$library_name );
	}
	
	function includeDir ( $dir )
	{
		$ch = opendir( $dir );
		while( ($file = readdir( $ch )) !== false )
		{
			if( $file != '.' && $file != '..' )
				if( is_dir( $dir.$file ) )
				{
					$this->includeDir( $dir.$file );
				}
				else
				{
					if( pathinfo($dir.$file, PATHINFO_EXTENSION) == EXT )
					{
						require_once( $dir.$file );
					}
				}
		}
		closedir( $ch );
	}	
	
	//DB
	
	function query ( $sql, $data = array() )
	{
		if( sizeof($data) )
		{
			foreach( $data as $index => $value )
				$data[ $index ] = $this->escape( $value );
			$sql = vsprintf( $sql, $data );
		}

		return mysql_query( $sql );
	}
	
	function escape ( $string )
	{
		return mysql_real_escape_string( $string );
	}
	
	function result ( $resource )
	{
		return mysql_fetch_object( $resource );
	}
	
	function result_array ( $resource )
	{
		return mysql_fetch_array( $resource );
	}
	
	function connect_db ()
	{
		@mysql_connect( DB_HOST, DB_USER, DB_PASS ) or die ( 'Estamos actualizando nuestros servidores, regresamos en un rato. (SRV)' );
		@mysql_select_db( DB_NAME ) or die ( 'Estamos actualizando nuestros servidores, regresamos en un rato. (DB)' );
		mysql_query('SET NAMES utf8');
	}
	
	public function __set($name, $value) 
	{ 
		$this->$name = $value; 
	} 
	
}
?>