<?php
/******************************************************************************
* Tecler - A simple framework for building tailor-made PHP template compilers
* https://github.com/mwandelt/Tecler
* 
* This content is released under the MIT License (MIT)
* Copyright (c) 2017, Martin Wandelt
******************************************************************************/

class Tecler {

	public $filterParamSeparator = ':';
	public $globalFilters = array ();
	public $idleMode = FALSE;
	public $includesDirectory; // base directory for resolving relative template paths
	public $removeHtmlCommentMarkers = TRUE;
	public $requiredFiles = array(); // external files required for running the generated PHP code
	public $scriptMode = FALSE;
	public $tagEndString = '}}';
	public $tagEndStringExpr = '}';
	public $tagFilterSeparator = '|';
	public $tagParamSeparator = ':';
	public $tagStartString = '{{';
	public $tagStartStringExpr = '{';
	
	private $cdataFilters = array (); // list of callbacks
	private $library = array (); // array of objects or classes which provide tag definitions
	private $usedClasses = array ();


	public function __construct( $includesDirectory = '' )
	{
		$this->includesDirectory = $includesDirectory;
	}


	public function register_class( $className )
	{
		if ( ! in_array( $className, $this->library ) )
		{
			$this->library[] = $className;
		}
	}
	
	
	public function compile( $code )
	{
		if ( $this->removeHtmlCommentMarkers )
		{
			$code = str_replace( '<!--' . $this->tagStartString, $this->tagStartString, $code );
			$code = str_replace( $this->tagEndString . '-->', $this->tagEndString, $code );
		}

		$arr = explode( $this->tagStartString, $code );
		$this->usedClasses = array ();
		$this->requiredFiles = array ();
		$result = $this->handle_cdata( $arr[0] );

		for ( $i = 1; $i < sizeof( $arr ); $i++ )
		{
			$pos = strpos( $arr[ $i ], $this->tagEndString );
			$result .= $this->handle_tag( substr( $arr[ $i ], 0, $pos ) );
			$result .= $this->handle_cdata( substr( $arr[ $i ], $pos + 2 ) );
		}

		foreach ( $this->usedClasses as $class => $value )
		{
			if ( method_exists( $class, 'get_requirements' ) )
			{
				$this->requiredFiles = array_merge( $this->requiredFiles, $class::get_requirements() );
			}
		}

		if ( empty( $this->requiredFiles ) )
		{
			return $result;
		}

		return "<?php \nrequire_once '" . implode( "';\nrequire_once '", $this->requiredFiles ) . "';\n?>\n" . $result;
	}


	public function compile_expression( $code, $isRawPhpCode = FALSE )
	{
		$arr = explode( $this->tagStartStringExpr, $code );
		$result = $this->handle_cdata( $arr[0],  ! $isRawPhpCode );

		for ( $i = 1; $i < sizeof( $arr ); $i++ )
		{
			$pos = strpos( $arr[ $i ], $this->tagEndStringExpr );
			$result .= $isRawPhpCode ? '' : ' . ';
			$result .= $this->handle_tag( substr( $arr[ $i ], 0, $pos ), TRUE );
			$result .= $isRawPhpCode ? '' : ' . ';
			$result .= $this->handle_cdata( substr( $arr[ $i ], $pos + 1 ), ! $isRawPhpCode );
		}

		return $isRawPhpCode ? $result : trim( str_replace( array( "'' . ", " . ''" ), '', $result ) );
	}
	
	
	public function add_global_filter( $filter, $param = '' )
	{
		$this->globalFilters[] = empty( $param ) ? $filter : $filter . $this->filterParamSeparator . $param;
	}

	
	public function remove_global_filter( $filter )
	{
		$this->globalFilters = array_filter( $this->globalFilters, 
			function( $value ) use( $filter ){ return strpos( $value, $filter ) !== 0; } );
	}

	
	public function reset_global_filters()
	{
		$this->globalFilters = array ();
	}
	
	
	public function include_file( $path )
	{
		$path = $path[0] == '/' ? $path : $this->includesDirectory . '/' . $path;
		return $this->compile( file_get_contents( $path ) );
	}
	
	
	public function start_idle_mode()
	{
		$this->idleMode = TRUE;
	}
	
	
	public function stop_idle_mode()
	{
		$this->idleMode = FALSE;
	}
	
	
	public function start_script_mode()
	{
		$this->scriptMode = TRUE;
	}
	
	
	public function stop_script_mode()
	{
		$this->scriptMode = FALSE;
	}


	private function handle_cdata( $code, $asExpression = FALSE )
	{
		if ( $this->idleMode || $this->scriptMode && ! $asExpression )
		{
			return '';
		}
		
		foreach ( $this->cdataFilters as $callback )
		{
			$code = call_user_func( $callback, $code );
		}

		return $asExpression ? "'" . addcslashes( $code, "'\\" ) . "'" : $code;
	}


	private function handle_tag( $args, $asExpression = FALSE )
	{
		# Is it a compiler directive? Handle appropriately.

		if ( $args[0] == '#' )
		{
			if ( $args == '#idle' )
			{
				$this->start_idle_mode();
				return '';
			}

			if ( $args == '#endidle' )
			{
				$this->stop_idle_mode();
				return '';
			}

			if ( $args == '#script' )
			{
				$this->start_script_mode();
				return '<?php ';
			}

			if ( $args == '#endscript' )
			{
				$this->stop_script_mode();
				return ' ?>';
			}

			if ( substr( $args, 0, 8 ) == '#include' )
			{
				$path = trim( substr( $args, 9 ) );
				return $this->include_file( $path );
			}

			if ( substr( $args, 0, 7 ) == '#filter' )
			{
				$param = trim( substr( $args, 8 ) );
				
				if ( empty( $param ) )
				{
					$this->globalFilters = array ();
				}
				else
				{
					$this->globalFilters = array_map( 'trim', explode( $this->tagFilterSeparator, $param ) );
				}
			}

			return '';
		}

		# It's not a directive, but a normal tag.

		if ( $this->idleMode || $args[0] == '-' )
		{
			return '';
		}

		if ( $args[0] == '!' )
		{
			$tag = substr( $args, 1 );
			$filter = '';
		}
		else
		{
			$pos = strpos( $args, $this->tagFilterSeparator, strrpos( $args, $this->tagEndStringExpr ) );
			$tag = $pos ? trim( substr( $args, 0, $pos ) ) : trim( $args );
			$filter = $pos ? trim( substr( $args, $pos + 1 ) ) : '';
		}

		$parts = explode( $this->tagParamSeparator, $tag, 2 ) + array( '', '' );
		list ( $name, $arg ) = array_map( 'trim', $parts );
		$lowerName = strtolower( $name );

		foreach ( $this->library as $class )
		{
			if ( $args[0] != '!' && method_exists( $class, "expression_{$lowerName}" ) )
			{
				$this->usedClasses[ $class ] = TRUE;
				$result = call_user_func( array ( $class, "expression_{$lowerName}" ), $arg, $this );
				
				if ( empty( $result ) )
				{
					return '';
				}

				$result = $this->filter_expression( $result, $filter );
				$result = $asExpression ? $result : "echo {$result}; ";
				return $this->scriptMode || $asExpression ? $result : "<?php {$result} ?>";
			}

			if ( $asExpression )
			{
				continue;
			}

			if ( method_exists( $class, "tag_{$lowerName}" ) )
			{
				$this->usedClasses[ $class ] = TRUE;
				$result = call_user_func( array ( $class, "tag_{$lowerName}" ), $arg, $this );

				if ( empty( $result ) )
				{
					return '';
				}

				return $this->scriptMode ? $result : "<?php {$result} ?>";
			}
		}

		foreach ( $this->library as $class )
		{
			if ( $args[0] != '!' && method_exists( $class, 'expression__default' ) )
			{
				$result = call_user_func( array ( $class, 'expression__default' ), $name, $arg, $this );

				if ( $result !== FALSE )
				{
					$this->usedClasses[ $class ] = TRUE;
					$result = $this->filter_expression( $result, $filter );
					$result = $asExpression ? $result : "echo {$result}; ";
					return $this->scriptMode || $asExpression ? $result : "<?php {$result} ?>";
				}
			}

			if ( $asExpression )
			{
				continue;
			}

			if ( method_exists( $class, 'tag__default' ) )
			{
				$result = call_user_func( array ( $class, 'tag__default' ), $name, $arg, $this );

				if ( $result !== FALSE )
				{
					$this->usedClasses[ $class ] = TRUE;
					return $this->scriptMode ? $result : "<?php {$result} ?>";
				}
			}
		}

		return '';
	}


	private function filter_expression( $expression, $filterString )
	{
		$filters = array_map( 'trim', explode( $this->tagFilterSeparator, $filterString ) );
		$filters = array_merge( $filters, $this->globalFilters );
		return $this->apply_filters( $expression, $filters );
	}


	private function apply_filters( $expression, $filters )
	{
		foreach ( $filters as $filter )
		{
			if ( $filter == '-' )
			{
				break;
			}

			$expression = $this->apply_filter( $expression, $filter );
		}

		return $expression;
	}


	private function apply_filter( $expression, $filter )
	{
		if ( empty( $filter ) )
		{
			return $expression;
		}

		$parts = explode( $this->filterParamSeparator, $filter, 2 ) + array ( '', '' );
		list ( $function, $args ) = array_map( 'trim', $parts );

		foreach ( $this->library as $class )
		{
			if ( method_exists( $class, "filter_{$function}" ) )
			{
				$this->usedClasses[ $class ] = TRUE;
				return call_user_func( array ( $class, "filter_{$function}" ), $expression, $args, $this );
			}
		}

		foreach ( $this->library as $class )
		{
			if ( method_exists( $class, 'filter__default' ) )
			{
				$result = call_user_func( array ( $class, 'filter__default' ), $expression, $function, $args, $this );

				if ( $result !== FALSE )
				{
					$this->usedClasses[ $class ] = TRUE;
					return $result;
				}
			}
		}

		if ( function_exists( $function ) )
		{
			return "{$function}( {$expression} )";
		}

		return $expression;
	}
}

// end of file tecler.php
