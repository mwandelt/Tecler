<?php

date_default_timezone_set('Europe/Berlin');

require_once __DIR__ . '/../tecler.class.php';
require_once __DIR__ . '/my_tags.class.php';
require_once __DIR__ . '/my_filters.class.php';

$tecler = new Tecler( __DIR__ );
$tecler->register_class('my_tags');
$tecler->register_class('my_filters');

$headline = 'Current Price List';
$remarks = 'Have a nice week!';
$products = array (
   array ( 'id' => 1, 'name' => 'Apple', 'price' => 1.6, 'lastOrder' => '2017-02-23' ),
   array ( 'id' => 2, 'name' => 'Banana', 'price' => 1.3, 'lastOrder' => '2017-04-11' ),
   array ( 'id' => 3, 'name' => 'Cherry', 'price' => 0.9, 'lastOrder' => '2017-01-08' )
);

$templateCode = file_get_contents( __DIR__ . '/template.html' );
$phpCode = $tecler->compile( $templateCode );

// uncomment the following line to output the generated PHP code
// die( $phpCode );

eval( '?' . '>' . $phpCode );

// end of file example.php
