-- phpMyAdmin SQL Dump
-- version 3.3.9.2
-- http://www.phpmyadmin.net
--
-- Servidor: localhost
-- Tiempo de generación: 09-12-2013 a las 21:39:36
-- Versión del servidor: 5.5.9
-- Versión de PHP: 5.3.6

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Base de datos: `opt_buscadores`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `Actividades`
--

CREATE TABLE `Actividades` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `titulo` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `imagen` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=4 ;

--
-- Volcar la base de datos para la tabla `Actividades`
--

INSERT INTO `Actividades` VALUES(1, 'Cabalgatas', 'cabalgatas.jpg', 'Las desérticas playas de Canelones te esperan, Cabalgatas Valiceras te propone disfrutar de una experiencia al aire libre cabalgando por el Área Protegida de Cabo Polonio y Médanos de Valizas (SNAP), declarado Reserva Mundial de Biósfera por UNESCO. Así como también por las lagunas costeras de Rocha: Laguna de Castillos, Laguna Negra; Área Protegida de Cerro Verde e Islas de La Coronilla, Parques Nacionales de Santa Teresa y San Miguel y los imperdibles Castillos de Piria.\r\n\r\nExcelentes monturas, dedicados guías bilingües conocedores de la belleza y peligros de la zona estarán a tu disposición para garantizarte una aventura sin riesgos innecesarios; y claro, los mejores caballos cuidadosamente seleccionados para cada tipo de jinete, hay para todos los niveles.\r\n\r\nHermosos paisajes, caminos históricos, arenas movedizas, playas e islas que fueron parajes del conocido pirata Etienne Moreau, españoles y portugueses. Avistamiento de fauna marina (ballenas francas, orcas, delfines, lobos y leones marinos y aves marinas) así como también la flora autóctona, y sobre todo: mucho, mucho aire puro!');
INSERT INTO `Actividades` VALUES(2, 'Aventura', 'aventuras.jpg', 'El ''turismo de aventura'' implica la exploración o el viaje a áreas remotas, donde el viajero puede esperar lo inesperado. El turismo de aventura está aumentando rápidamente su popularidad ya que los turistas buscan vacaciones inusuales, diferentes de las típicas vacaciones en la playa.\r\nEl turismo de aventura, es dirigido para todos los turistas, pero en especial para aquellos que les guste combinar sus actividades con el aire libre.\r\nEste tipo de turismo también se relaciona directamente con el deporte de aventura o riesgo, donde la gente tiene por objetivo pasar momentos de adrenalina a costo de un porcentaje de riesgo.\r\nEl turismo de aventura tiene como objetivo principal el fomento de las actividades de aventura en la naturaleza. Es el hecho de visitar o alojarse en zonas donde se pueden desarrollar los llamados deportes de aventura o turismo activo.\r\nLa diferencia de turismo de aventura y deportes de aventura, estaría en que en la segunda actividad hace falta una preparación mínima y un equipo apropiado, como lo es practicar rappel, escalada, carreras de aventura o montañismo.');
INSERT INTO `Actividades` VALUES(3, 'Pesca', 'pesca.jpg', 'Las propuestas del llamado turismo marinero que permiten acompañar a pescadores durante una jornada de trabajo, conocer sus costumbres y gastronomía afloran en las costas y, además de suponer una fuente de ingresos para la flota, contribuyen a dar visibilidad a estos profesionales del mar. Otras imágenes 3 Fotos Aparte del embarque, el turismo marinero incluye visitas a lonjas, cursos de cocina e incluso talleres para niños Al igual que ocurrió con el turismo rural o el enológico, el denominado "pesca-turismo" o marinero surge como una opción de empleo o para completar las rentas de un sector, gracias a la difusión de un oficio tradicional ligado a la producción de un alimento de valor, como el pescado o marisco, y al interés que su día a día suscita entre forasteros y locales. El Gobierno, las Administraciones autonómicas, el Congreso de los Diputados y el Senado han abogado por impulsar el "pesca-turismo" en el litoral, para quienes busquen algo más que tomar el sol en la playa y precisamente por su potencial para diversificar la actividad del segmento pesquero.\r\n');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `Cabanias`
--

CREATE TABLE `Cabanias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `titulo` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `imagen` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=5 ;

--
-- Volcar la base de datos para la tabla `Cabanias`
--

INSERT INTO `Cabanias` VALUES(1, 'Dos personas', 'dos-personas.jpg', 'Ofrece a sus huéspedes sus confortables instalaciones para disfrutar de unas inolvidables vacaciones en armonía con la naturaleza.\r\n\r\nEstar y terraza con mesas y sillas.\r\nBaño privado con agua caliente.\r\nKichenette con Vajilla.\r\nHeladera.\r\nVentilador.\r\nFrazadas.\r\nEstufa a leña.\r\n\r\nEl restaurant ofrece una gran variedad de platos,\r\ndesde una Pizza Italiana a una excelente cocina Mediterranea elaborada por nuestro Chef Gerardo.\r\n\r\nSERVICIOS:\r\nSpa y Jacuzzi. (Piscina climatizada – hidromasaje – sala de musculación – vestuarios y solarium)\r\nMasajes.\r\nRestaurant.\r\nDesayuno Buffet. (Café – te –leche – pan casero – scones – tortas – manteca – mermelada y licuado frutas)\r\nÁrea de Parrilleros.\r\npiscina exterior)\r\nJuegos de mesa.\r\nCancha fútbol y volley.\r\nAlquiler de bicicletas.\r\n\r\nOPCIONALES:\r\nAlquiler de batas para mayores y niños.\r\nLavandería.\r\nServicio de mucama (incluye ropa de cama y toallas).');
INSERT INTO `Cabanias` VALUES(2, 'Tres personas', 'tres-personas.jpg', 'Ofrece a sus huéspedes sus confortables instalaciones para disfrutar de unas inolvidables vacaciones en armonía con la naturaleza.\r\n\r\nEstar y terraza con mesas y sillas.\r\nBaño privado con agua caliente.\r\nKichenette con Vajilla.\r\nHeladera.\r\nVentilador.\r\n\r\nEl restaurant ofrece una gran variedad de platos,\r\ndesde una Pizza Italiana a una excelente cocina Mediterranea elaborada por nuestro Chef Gerardo.\r\n\r\nSERVICIOS:\r\nSpa y Jacuzzi. (Piscina climatizada – hidromasaje – sala de musculación – vestuarios y solarium)\r\nMasajes.\r\nRestaurant.\r\nDesayuno Buffet. (Café – te –leche – pan casero – scones – tortas – manteca – mermelada y licuado frutas)\r\nÁrea de Parrilleros.\r\n\r\nOPCIONALES:\r\nAlquiler de ropa de cama.\r\nAlquiler de toallas.\r\nAlquiler de batas para mayores y niños.\r\nLavandería.\r\nServicio de mucama (incluye ropa de cama y toallas).');
INSERT INTO `Cabanias` VALUES(3, 'Cuatro personas', 'cuatro-personas.jpg', 'Ofrece a sus huéspedes sus confortables instalaciones para disfrutar de unas inolvidables vacaciones en armonía con la naturaleza.\r\n\r\nEstar y terraza con mesas y sillas.\r\nBaño privado con agua caliente.\r\nKichenette con Vajilla.\r\nHeladera.\r\nVentilador.\r\nFrazadas.\r\nEstufa a leña.\r\n\r\nEl restaurant ofrece una gran variedad de platos,\r\ndesde una Pizza Italiana a una excelente cocina Mediterranea elaborada por nuestro Chef Gerardo.\r\n\r\nSERVICIOS:\r\nSpa y Jacuzzi. (Piscina climatizada – hidromasaje – sala de musculación – vestuarios y solarium)\r\nMasajes.\r\nRestaurant.\r\nDesayuno Buffet. (Café – te –leche – pan casero – scones – tortas – manteca – mermelada y licuado frutas)\r\nÁrea de Parrilleros.\r\nParque para niños. (Barco pirata – hamacas – mini golf y\r\npiscina exterior)\r\nJuegos de mesa.\r\nCancha fútbol y volley.\r\nAlquiler de bicicletas.\r\nEstacionamiento privado.\r\n\r\nOPCIONALES:\r\nAlquiler de ropa de cama.\r\nAlquiler de toallas.\r\nAlquiler de batas para mayores y niños.\r\nLavandería.\r\nServicio de mucama (incluye ropa de cama y toallas).');
INSERT INTO `Cabanias` VALUES(4, 'Cinco personas', 'cinco-personas.jpg', 'Ofrece a sus huéspedes sus confortables instalaciones para disfrutar de unas inolvidables vacaciones en armonía con la naturaleza.\r\n\r\nEstar y terraza con mesas y sillas.\r\nBaño privado con agua caliente.\r\nKichenette con Vajilla.\r\nHeladera.\r\nVentilador.\r\nFrazadas.\r\nEstufa a leña.\r\n\r\nEl restaurant ofrece una gran variedad de platos,\r\ndesde una Pizza Italiana a una excelente cocina Mediterranea elaborada por nuestro Chef Gerardo.\r\n\r\nSERVICIOS:\r\nSpa y Jacuzzi. (Piscina climatizada – hidromasaje – sala de musculación – vestuarios y solarium)\r\nMasajes.\r\nRestaurant.\r\nDesayuno Buffet. (Café – te –leche – pan casero – scones – tortas – manteca – mermelada y licuado frutas)\r\nÁrea de Parrilleros.\r\nParque para niños. (Barco pirata – hamacas – mini golf y\r\npiscina exterior)\r\nJuegos de mesa.\r\nCancha fútbol y volley.\r\nAlquiler de bicicletas.\r\nEstacionamiento privado.\r\n\r\nOPCIONALES:\r\nAlquiler de ropa de cama.\r\nAlquiler de toallas.\r\nAlquiler de batas para mayores y niños.\r\nLavandería.\r\nServicio de mucama (incluye ropa de cama y toallas).');
