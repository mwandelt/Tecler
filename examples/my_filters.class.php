<?php

class my_filters {
	

	static function filter_date( $expression, $args )
	{
		return "date( '{$args}', {$expression} )";
	}


	static function filter_price( $expression, $args )
	{
		return "'$' . number_format( {$expression}, 2 )";
	}

}

// end of file my_filters.class.php
