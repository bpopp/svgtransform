# svgtransform
Simple PHP Library to Remove Transforms from SVG Graphics in order to work around a bug in Adobe Illustrator CC 2018. 

Many SVG editors use a transform feature to adjust the size of objects in the canvas. For example: 

```xml
<g transform="scale(0.1,0.1)" id="g12">
  <path d="m 2168.34,2540.19 -6.47,0 -8.64,-1.07 -11.88,1.07 -2.16,1.08 -17.27,5.4 -16.73,9.18 0,0 -22.14,19.97 -8.63,9.72 -4.32,5.39 -9.18,9.72 -14.04,8.64 0,0 -3.77,2.16 -15.12,6.47 -7.55,3.24 -14.04,10.26 0,0 -1.08,1.62" 
        inkscape:connector-curvature="0" 
        id="path14" 
        style="fill:none;stroke:#231f20;stroke-width:7.5;stroke-linecap:square;stroke-linejoin:round;stroke-miterlimit:4;stroke-opacity:1;stroke-dasharray:none"/>
</g>
```

When opening this graphic in most viewers/editors, the transform in the *g* element would be used to reduce the x/y coordinates in the d attribute of each `path` and the stroke-width of the `style` attribute by 10% `(.1)`. 

Unfortunately, Illustrator CC 2018 has a bug that causes it to correctly resize the `d` attribute and completely ignore the `stroke-width` attribute creating a graphic that looks like:

![Bad SVG](images/badsvg.png) 

To work around this bug, I created this PHP class that will remove the transform, and use the x,y ratio to manually adjust both the coordinates and stroke-width of each child `path` element. This allows the graphic to be viewable/editable in Illustrator, but still render correctly in every other editor/viewer (that I've tested). The fixed image looks like this:

![Good SVG](images/goodsvg.png) 
                                           
### Usage
Simplest usage would be something like:

```php
include_once ( "svgtransform.class.php" );
$fixer = new SVGTransformFix ( $tempDir . "/" . $tempFile );
$out =  $fixer->Transform();   // returns transformed SVG in a string
```

Or in a batch

```php
include_once ( "svgtransform.class.php" );
foreach (new DirectoryIterator($tempIn ) as $fileInfo)
{
    if($fileInfo->isDot()) continue;
    if($fileInfo->getExtension() != "svg" ) continue;

    $fixer = new SVGTransformFix ( $tempIn . "/" . $fileInfo->getFilename() );
    $out =  $fixer->Transform();

    if ( $out );
    {
        new dbug ( "Writing " . $fileInfo->getFilename() );
        file_put_contents ( $tempOut . "/" . $fileInfo->getFilename(), $out );
    }
}
```
