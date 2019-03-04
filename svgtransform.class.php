<?php
class SVGTransformFix
{
    var $in;
    var $coordTransform = [];
    var $strokeTransform = .1;

    function __construct( $inFile )
    {
        $this->in = $inFile;
    }

    function Transform ()
    {
        $source = file_get_contents( $this->in ) ;

        // this is a little lazy, but I had trouble getting simplexml_load_string to work w/ different types of ns declarations
        $source = preg_replace  ( "/xmlns=\".*?svg\"/", "", $source );
        $xml = simplexml_load_string( $source );
        
        if ( !$xml ) return $source;

        
        $results = $xml->xpath("//g[starts-with(@transform,'scale')]");

        //print_r ( $results );

        foreach ( $results as $node )
        {
            $this->coordTransform = null;
            $this->strokeTransform = null;

            $attrs = $node->attributes();
            $transform =  (string) $attrs['transform'];


            if ( preg_match ( '|scale\((.*?)\,(.*?)\)|mis', $transform, $matches ) )
            {
                $this->coordTransform = [ $matches[1], $matches[2] ];
                $this->strokeTransform = $matches[1];
            }
            elseif ( preg_match ( '|scale\((.*?)\)|mis', $transform, $matches ) )
            {
                $this->coordTransform = $matches[1];
                $this->strokeTransform = $matches[1];
            }
            // unset the @transform since we will be resetting the values manually in the next step
            $node['transform'] = '';

            // for each subordinate path, we will manually reset each set of coordinates
            $paths = $node->xpath ( "descendant::path" );
            foreach ( $paths as $path )
            {
                $attrs = $path->attributes();
                $coords = (string) $attrs['d'];
                $style = (string) $attrs['style'];

                //new dbug ( $this->coordTransform );
                $path['d'] = $this->fixcoords ( $coords );

                // currently only handling the stroke-width (which seems to work)
                if ( $style )
                {
                    $style = preg_replace_callback ( '|stroke-width:(.*?);|mis',
                        function ( $matches ) { return sprintf ( "stroke-width:%s;",$matches[1] * $this->strokeTransform); },
                        $style );
                    $path['style'] = $style;
                }
            }


        }

        //exit;

        $dom = dom_import_simplexml($xml)->ownerDocument;
        $dom->formatOutput = true;
        $dom->documentElement->setAttribute("xmlns", "http://www.w3.org/2000/svg" );
        return $dom->saveXML();

        //return $xml->asXML();
    }

    /***
     * iterates through the list of commands and coordinates and multiples any coordinates
     * by their corresponding x/y transform values (hopefully captured earlier).
     *
     * returns: a corrected coordinate string
     */

    function fixcoords ( $coords )
    {
        if ( !$this->coordTransform ) return;

//        $coordlist = explode(" ", $coords);

        preg_match_all ( "/([a-zA-Z])([^a-zA-Z]*)/mis", $coords, $matches );


        for ( $c = 0; $c<=sizeof($matches[0]); $c++ )
        {
            $command  = $matches[1][$c];
            $value = $matches[2][$c];


            if ( in_array ( $command, ['m', 'M', 'l', 'L', 'c', 'C', 'H', 'V', 'v', 'h'] ) )
            {
                $out[] = $command;
                $values = explode ( " ", trim($value) );

                foreach ( $values as $value )
                {
                    if (  strstr ( $value, ',' ) )
                    {
                        list ( $x, $y ) = explode ( ",", $value );
                        if ( is_array ( $this->coordTransform ) )
                        {
                            $out[] = sprintf ( '%s,%s',$x*$this->coordTransform[0],$y*$this->coordTransform[1] );
                        }
                        elseif ( $this->coordTransform )
                        {
                            $out[] = sprintf ( '%s,%s', $x*$this->coordTransform,$y*$this->coordTransform );
                        }
                    }
                    else
                    {
                        if ( $this->coordTransform )
                        {
                            $out[] = sprintf ( '%s', $value*$this->coordTransform );
                        }
                    }
                }

            }
            else
            {
                $out[] = $command . " " . $value;
            }

        }
        return join ( " ", $out );
    }
}
