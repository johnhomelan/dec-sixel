<?php
declare(strict_types=1);
require_once 'vendor/autoload.php';

use HomeLan\Retro\DEC\Terminal\Gfx\SixelConverter;
/**
 * SixelConverter Demo Script
 * 
 * This script demonstrates how to use the SixelConverter class
 * to convert an image file to Sixel format for terminal display.
 * 
 * Usage: php demo.php /path/to/image.jpg
 * 
 * Note: Your terminal must support Sixel graphics for this to work.
 * Compatible terminals include: xterm with sixel support, mlterm, Mintty, and Contour.
 */


// Check if an image path was provided
if ($argc < 2) {
    echo "Usage: php demo.php /path/to/image.jpg\n";
    echo "Note: Your terminal must support Sixel graphics to see the image.\n";
    exit(1);
}

$sImagePath = $argv[1];

// Validate file exists
if (!file_exists($sImagePath)) {
    echo "Error: File not found: $sImagePath\n";
    exit(1);
}

try {
    // Check terminal capabilities
    echo "Please ensure your terminal supports Sixel graphics.\n";
    echo "Processing image: $sImagePath\n";
    
    // Create converter instance
    $oConverter = new SixelConverter();
    
    // Configure converter
    $oConverter->setMaxDimensions(200, 300)  // Set reasonable terminal size
               ->setColorCount(32)           // Use 64 colors for better terminal compatibility
               ->loadImage($sImagePath);
    
    // Convert and output
    echo "Displaying image in Sixel format:\n";
    echo $oConverter->convertToSixel();
    echo "\n\nIf you don't see an image above, your terminal likely doesn't support Sixel graphics.\n";
    echo "Try running this in xterm with '-ti 340' option, mlterm, Mintty or another Sixel-compatible terminal.\n";
    
} catch (Exception $oException) {
    echo "Error: " . $oException->getMessage() . "\n";
    exit(1);
}
