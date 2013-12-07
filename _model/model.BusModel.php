<?
class BusModel
{
	var $mg;
	
	function __construct ()
	{
		$this->mg = &get_instance();
	}
	
	function getParadasCercanas ( $lat, $lon, $radius = 100 )
	{
		$sql = '
			SELECT 
				paradas_id, paradas_lat, paradas_lon
			FROM
				paradas
			WHERE
				DISTANCE( "%s", "%s", paradas_lat, paradas_lon ) <= "%s"
		';
		
		return $this->mg->query( $sql, array( $lat, $lon, $radius ) );
	}
	//Muy particular.... no devuelve un res, sino un array
	function getVariantesParadasOrigenDestino ( $lat_o, $lon_o, $lat_d, $lon_d, $paradas_o, $paradas_d, $sentido )
	{
		
		$sql_paradas_o = array();
		$sql_paradas_d = array();
	
		$sql_paradas_o = implode( ', ', $paradas_o );
		$sql_paradas_d = implode( ', ', $paradas_d );
		
		$sql = '
		CREATE TEMPORARY TABLE tmp_paradas_variantes AS (
			SELECT
				paradas_variantes_variante,
				paradas_id,
				paradas_lat,
				paradas_lon
			FROM
				paradas,
				variantes,
				paradas_variantes
			WHERE
				paradas_variantes_parada IN ( '.$sql_paradas_d.' ) AND
				paradas_variantes_variante IN
					(
					SELECT paradas_variantes_variante FROM paradas_variantes WHERE paradas_variantes_parada IN ( '.$sql_paradas_o.' )
					) AND
				variantes_id = paradas_variantes_variante AND
				variantes_sentido = "'.$sentido.'" AND
				paradas_id = paradas_variantes_parada		
			)	
		';
		//Solo devuelve las del destino.... CORREJIRRR
		mysql_query( $sql ) or die( mysql_error() );
		
		$res = mysql_query( 'select * from tmp_paradas_variantes' );
		while( $reg = mysql_fetch_array($res) )
			print_r( $reg );		
		//Parada mas cercana en origen
		$sql = '
			SELECT *, DISTANCE( paradas_lat, paradas_lon, "'.$lat_o.'", "'.$lon_o.'" ) as dist 
			FROM tmp_paradas_variantes
			ORDER BY dist ASC
		';
		
		$res = mysql_query( $sql ) ;
		
		$variantes = array();
		while( $reg = mysql_fetch_object( $res ) )
			if( !isset($variantes[ $reg->paradas_variantes_variante ]['parada_o']) )
				$variantes[ $reg->paradas_variantes_variante ]['parada_o'] = array( 
			 																'id' => $reg->paradas_id, 
																			'lat' => $reg->paradas_lat,
																			'lon' => $reg->paradas_lon );
		//Parada mas cercana en destino
		$sql = '
			SELECT *, DISTANCE( paradas_lat, paradas_lon, "'.$lat_d.'", "'.$lon_d.'" ) as dist 
			FROM tmp_paradas_variantes
			ORDER BY dist ASC
		';
		
		$res = mysql_query( $sql ) ;
	
		while( $reg = mysql_fetch_object( $res ) )
			if( !isset($variantes[ $reg->paradas_variantes_variante ]['parada_d']) )
				$variantes[ $reg->paradas_variantes_variante ]['parada_d'] = array( 
			 																'id' => $reg->paradas_id, 
																			'lat' => $reg->paradas_lat,
																			'lon' => $reg->paradas_lon );
		print_r( $variantes );																	
		return $variantes;
	}
	
	function testRecorrido ()
	{
		$sql = '
			SELECT *
FROM recorridos
WHERE recorridos_variante =2916
		';
		return $this->mg->query( $sql ) ;
	}	
}
?>