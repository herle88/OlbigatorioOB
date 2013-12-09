<?
$menues = array(
	array(
		'titulo' => 'Camping',
		'url' => SITE_ROOT.'camping/'
	),
	array(
		'titulo' => 'Cabañas',
		'url' => SITE_ROOT.'cabanias/',
		'sub_items' => array(
			'items' => array(
				array(
					'titulo' => 'Dos personas',
					'url' => SITE_ROOT.'cabanias/1-dos-personas'
				),
				array(
					'titulo' => 'Tres personas',
					'url' => SITE_ROOT.'cabanias/2-tres-personas'
				),
				array(
					'titulo' => 'Cuatro personas',
					'url' => SITE_ROOT.'cabanias/3-cuatro-personas'
				),
				array(
					'titulo' => 'Cinco personas',
					'url' => SITE_ROOT.'cabanias/4-cinco-personas'
				)				
			)
		)
	),
	array(
		'titulo' => 'Casas Rodantes',
		'url' => SITE_ROOT.'casas_rodantes/'
	),
	array(
		'titulo' => 'Actividades',
		'url' => SITE_ROOT.'actividades/',
		'sub_items' => array(
			'items' => array(
				array(
					'titulo' => 'Pesca',
					'url' => SITE_ROOT.'actividades/3-pesca'
				),
				array(
					'titulo' => 'Aventuras',
					'url' => SITE_ROOT.'actividades/2-aventuras'
				),
				array(
					'titulo' => 'Cabalgatas',
					'url' => SITE_ROOT.'actividades/1-cabalgatas'
				)			
			)
		)
	)
);
?>