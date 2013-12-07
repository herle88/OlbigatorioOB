<?
class Bus 
{
	var $mg;
	var $model;
	var $stops_radius = 400;
	
	function __construct ()
	{
		$this->mg = &get_instance();
		$this->model = $this->mg->loadModel( 'BusModel' );
	}
	
	function getParadasCercanas ( $lat_o, $lon_o )
	{
		$res = $this->model->getParadasCercanas( $lat_o, $lon_o, $this->stops_radius );
		
		$stops = array();
		while( $reg = $this->mg->result( $res ) )
		{
			$stops[] = array( 'id' => $reg->paradas_id, 'lat' => $reg->paradas_lat, 'lon' => $reg->paradas_lon );
		}
		
		return $stops;
	}
	
	function testRecorrido()
	{
		$res = $this->model->testRecorrido();
		$puntos = array();
		while( $reg = mysql_fetch_object( $res ) )
		{
			$puntos[] = array( 'lat' => $reg->recorridos_lat, 'lon' => $reg->recorridos_lon );
		}
		return $puntos;
	}
	
	function getBusesHaciaDestino ( $lat_o, $lon_o, $lat_d, $lon_d )
	{
		$sentido = $this->sentidoBus( $lat_o, $lon_o, $lat_d, $lon_d );
		$paradas_origen = $this->getParadasCercanas( $lat_o, $lon_o );
		$paradas_destino = $this->getParadasCercanas( $lat_d, $lon_d );
		
		$paradas_origen_ids = array();
		$paradas_destino_ids = array();
		
		foreach( $paradas_origen as $parada )
			$paradas_origen_ids[] = $parada['id'];
		
		foreach( $paradas_destino as $parada )
			$paradas_destino_ids[] = $parada['id'];
		
		return $this->model->getVariantesParadasOrigenDestino( $lat_o, $lon_o, $lat_d, $lon_d, $paradas_origen_ids, $paradas_destino_ids, $sentido );
		
	}
	
	function anguloOrientacion ($lat1, $lon1, $lat2, $lon2) {
		$lat1 = deg2rad($lat1);
		$lat2 = deg2rad($lat2);
		$dLon = deg2rad($lon2-$lon1);

		$dPhi = log(tan($lat2/2+pi()/4)/tan($lat1/2+pi()/4));
		if (abs($dLon) > pi()) $dLon = $dLon>0 ? -(2*pi()-$dLon) : (2*pi()+$dLon);
		$brng = atan2($dLon, $dPhi);
		return (rad2deg($brng)+360) % 360;
	}
	
	function  orientacionCardinal ( $angulo )
	{
		if( $angulo >= 0 && $angulo < 22.5 )
			return 'N';
		else if( $angulo >= 22.5 && $angulo < 67.5 )
			return 'NE';
		else if( $angulo >= 67.5 && $angulo < 112.5 )
			return 'E';
		else if( $angulo >= 112.5 && $angulo < 157.5 )
			return 'SE';
		else if( $angulo >= 157.5 && $angulo < 202.5 )
			return 'S';
		else if( $angulo >= 202.5 && $angulo < 247.5 )
			return 'SO';
		else if( $angulo >= 247.5 && $angulo < 292.5 )
			return 'O';
		else if( $angulo >= 292.5 && $angulo < 337.5 )
			return 'NO';
		else if( $angulo >= 337.5 && $angulo < 360.1 )
			return 'N';
	}
	
	function sentidoBus ( $lat_o, $lon_o, $lat_d, $lon_d )
	{
		$angulo = $this->anguloOrientacion( $lat_o, $lon_o, $lat_d, $lon_d );
		$pCardinal = $this->orientacionCardinal( $angulo );
		
		switch( $pCardinal )
		{
			case 'E':
			case 'SE':		
			case 'S':
			case 'SO':
				return 'A';
				break;
			default:
				return 'B';
				break;
		}
	}
	
}
?>