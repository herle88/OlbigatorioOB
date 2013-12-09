<?
class Datos
{
	var $mg;
	
	function __construct ()
	{
		$this->mg = &get_instance();
	}
	
	function getCabanias ()
	{
		$sql = '
			SELECT 
				*
			FROM
				Cabanias
		';
		
		return $this->mg->query( $sql, array(  ) );
	}
	
	function getCabania ($id)
	{
		$sql = '
			SELECT 
				*
			FROM
				Cabanias
			WHERE
				id = "%s"
		';
		
		return $this->mg->result($this->mg->query( $sql, array( $id ) ));
	}	
	
	function getActividades ()
	{
		$sql = '
			SELECT 
				*
			FROM
				Actividades
		';
		
		return $this->mg->query( $sql, array(  ) );
	}
	
	function getActividad ($id)
	{
		$sql = '
			SELECT 
				*
			FROM
				Actividades
			WHERE
				id = "%s"
		';
		
		return $this->mg->result($this->mg->query( $sql, array( $id ) ));
	}
}
?>