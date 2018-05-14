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
        $xml = simplexml_load_file($this->in);
        $results = $xml->xpath("//svg:g[starts-with(@transform,'scale')]");
        foreach ( $results as $node )
        {
            $attrs = $node->attributes();
            $transform =  (string) $attrs['transform'];
            if ( preg_match ( '|scale\((.*?)\,(.*?)\)|mis', $transform, $matches ) )
            {
                $this->coordTransform = [ $matches[1], $matches[2] ];
                $this->strokeTransform = $matches[1];
            }
            // unset the @transform since we will be resetting the values manually in the next step
            $node['transform'] = '';

            // for each subordinate path, we will manually reset each set of coordinates
            $paths = $node->xpath ( "descendant::svg:path" );
            foreach ( $paths as $path )
            {
                $attrs = $path->attributes();
                $coords = (string) $attrs['d'];
                $style = (string) $attrs['style'];

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
        return $xml->asXML();
    }

    /***
     * iterates through the list of commands and coordinates and multiples any coordinates
     * by their corresponding x/y transform values (hopefully captured earlier).
     *
     * returns: a corrected coordinate string
     */

    function fixcoords ( $coords )
    {
        $coordlist = explode(" ", $coords);
        $out = array();
        foreach ( $coordlist as $coord )
        {
            // handle move, line, and curve commands
            if ( in_array( $coord, ['m', 'M', 'l', 'L', 'c', 'C'] ) )
            {
                $out[] = $coord;
            }
            // anything else should be a coordinate
            elseif ( strstr ( $coord, ',' ) )
            {
                list ( $x, $y ) = explode ( ",", $coord );
                $out[] = sprintf ( '%s,%s', $x*$this->coordTransform[0],$y*$this->coordTransform[1] );
            }
        }
        return join ( ' ', $out );
    }

}

