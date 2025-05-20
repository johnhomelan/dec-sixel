SixelConverter
==============


PHP Class for reading in image files, and producing the output for a terminal that supports sixel, to display that image.

Install
-------

composer require homelan/dec-sixel

Usage
-----
There is one class prodvided by the package, which reads in an image file, and produces the sixel escape sequence.

```
    require_once 'vendor/autoload.php';
    use HomeLan\Retro\DEC\Terminal\Gfx\SixelConverter;

    // Create converter instance
    $oConverter = new SixelConverter();
    
    // Configure converter
    $oConverter->setMaxDimensions(200, 300)  // Set reasonable terminal size
               ->setColorCount(32)           // Use 64 colors for better terminal compatibility
               ->loadImage('some-image.png');
    
    // Convert and output
    echo "Displaying image in Sixel format:\n";
    echo $oConverter->convertToSixel();
```
Run length encoding is not supported, however it is disabled by default.  As it consumes more CPU time, however it saves
bendwidth.

```
    require_once 'vendor/autoload.php';
    use HomeLan\Retro\DEC\Terminal\Gfx\SixelConverter;

    // Create converter instance
    $oConverter = new SixelConverter();

    $sImage = file_get_contents(''some-image.png');
    
    // Configure converter
    $oConverter->setMaxDimensions(200, 300)  // Set reasonable terminal size
               ->setColorCount(32)           // Use 64 colors for better terminal compatibility
               ->enableRunlengthEncoding()   // Enables run length encoding
               ->setImage($sImage);
    
    // Convert and output
    echo "Displaying image in Sixel format:\n";
    echo $oConverter->convertToSixel();
```
