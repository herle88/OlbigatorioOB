<?
class Converter 
{
	
    var $easting = 0.0;
    var $northing = 0.0;
    var $zone = 0;	
	var $arc = 0.0;
    var $mu = 0.0;
    var $ei = 0.0;
    var $ca = 0.0;
    var $cb = 0.0;
    var $cc = 0.0;
    var $cd = 0.0;
    var $n0 = 0.0;
    var $r0 = 0.0;
    var $_a1 = 0.0;
    var $dd0 = 0.0;
    var $t0 = 0.0;
    var $Q0 = 0.0;
    var $lof1 = 0.0;
    var $lof2 = 0.0;
    var $lof3 = 0.0;
    var $_a2 = 0.0;
    var $phi1 = 0.0;
    var $fact1 = 0.0;
    var $fact2 = 0.0;
    var $fact3 = 0.0;
    var $fact4 = 0.0;
    var $zoneCM = 0.0;
    var $_a3 = 0.0;
    var $b = 6356752.314;
    var $a = 6378137;
    var $e = 0.081819191;
    var $e1sq = 0.006739497;
    var $k0 = 0.9996;	
	
	var $southernHemisphere = "ACDEFGHJKLM";
	
	function __construct ()
	{
		
	}
	
    function convertUTMToLatLong( $UTM )
    {
		$latlon = array();

		$utm = explode(' ', $UTM);
		
		$this->zone = (int)$utm[0];
		
		$latZone = $utm[1];
		
		$this->easting = doubleval($utm[2]);
		
		$this->northing = doubleval($utm[3]);
		
		$hemisphere = $this->getHemisphere($latZone);
		
		$latitude = 0.0;
		
		$longitude = 0.0;
		
		if ($hemisphere == "S")
		{
			$this->northing = 10000000 - $this->northing;
		}
		
		$this->setVariables();
		
		$latitude = 180 * ($this->phi1 - $this->fact1 * ($this->fact2 + $this->fact3 + $this->fact4)) / pi();
		
		if ($this->zone > 0)
		{
			$this->zoneCM = 6 * $this->zone - 183.0;
		
		}
		else
		{
			$this->zoneCM = 3.0;
		}

		$longitude = $this->zoneCM - $this->_a3;
		
		if ($hemisphere == "S")
		{
			$latitude = -$latitude;
		}
		
		$latlon[0] = $latitude;

		$latlon[1] = $longitude;
		
		return $latlon;
		
		
		
		}	
	
	function getHemisphere($latZone)
	{
		$hemisphere = "N";
	
		if ( strpos( $this->southernHemisphere, $latZone ) !== false )
		{
			$hemisphere = "S";
		}
		return $hemisphere;
	}	
	
	function setVariables ()
	{
		$this->arc = $this->northing / $this->k0;
		$this->mu = $this->arc
          / ($this->a * (1 - pow($this->e, 2) / 4.0 - 3 * pow($this->e, 4) / 64.0 - 5 * pow($this->e, 6) / 256.0));
		
		$this->ei = (1 - pow((1 - $this->e * $this->e), (1 / 2.0)))
          / (1 + pow((1 - $this->e * $this->e), (1 / 2.0)));

		$this->ca = 3 * $this->ei / 2 - 27 * pow($this->ei, 3) / 32.0;

		$this->cb = 21 * pow($this->ei, 2) / 16 - 55 * pow($this->ei, 4) / 32;

		$this->cc = 151 * pow($this->ei, 3) / 96;

		$this->cd = 1097 * pow($this->ei, 4) / 512;

		$this->phi1 = $this->mu + $this->ca * sin(2 * $this->mu) + $this->cb * sin(4 * $this->mu) + $this->cc * sin(6 * $this->mu) + $this->cd
          * sin(8 * $this->mu);

		$this->n0 = $this->a / pow((1 - pow(($this->e * sin($this->phi1)), 2)), (1 / 2.0));

		$this->r0 = $this->a * (1 - $this->e * $this->e) / pow((1 - pow(($this->e * sin($this->phi1)), 2)), (3 / 2.0));

		$this->fact1 = $this->n0 * tan($this->phi1) / $this->r0;

		$this->_a1 = 500000 - $this->easting;

		$this->dd0 = $this->_a1 / ($this->n0 * $this->k0);

		$this->fact2 = $this->dd0 * $this->dd0 / 2;

		$this->t0 = pow(tan($this->phi1), 2);

		$this->Q0 = $this->e1sq * pow(cos($this->phi1), 2);

		$this->fact3 = (5 + 3 * $this->t0 + 10 * $this->Q0 - 4 * $this->Q0 * $this->Q0 - 9 * $this->e1sq) * pow($this->dd0, 4) / 24;

		$this->fact4 = (61 + 90 * $this->t0 + 298 * $this->Q0 + 45 * $this->t0 * $this->t0 - 252 * $this->e1sq - 3 * $this->Q0 * $this->Q0)
			* pow($this->dd0, 6) / 720;

		$this->lof1 = $this->_a1 / ($this->n0 * $this->k0);

		$this->lof2 = (1 + 2 * $this->t0 + $this->Q0) * pow($this->dd0, 3) / 6.0;

		$this->lof3 = (5 - 2 * $this->Q0 + 28 * $this->t0 - 3 * pow($this->Q0, 2) + 8 * $this->e1sq + 24 * pow($this->t0, 2))
			* pow($this->dd0, 5) / 120;

		$this->_a2 = ($this->lof1 - $this->lof2 + $this->lof3) / cos($this->phi1);

		$this->_a3 = $this->_a2 * 180 / pi();
	}
	
}

?>