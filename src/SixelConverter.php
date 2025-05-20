<?php
declare(strict_types=1);

namespace HomeLan\Retro\DEC\Terminal\Gfx;

/**
 * SixelConverter Class
 * 
 * A PHP class that converts image files to Sixel format for display in terminals
 * that support the Sixel graphics protocol.
 * 
 * @author John Brown
 * @version 1.0
 */
class SixelConverter {
    /**
     * The GD image resource
     */
    protected \GdImage|null $rImage = null;
    
    /**
     * Maximum width for the output image
     */
    protected int $iMaxWidth = 800;
    
    /**
     * Maximum height for the output image
     */
    protected int $iMaxHeight = 500;
    
    /**
     * Number of colors to quantize to
     */
    protected int $iColorCount = 256;
    
    /**
     * Palette of colors used in the Sixel output
     * 
     * @var array<int, array{r: int, g: int, b: int}>
     */
    protected array $aPalette = [];

    /**
     * Sets if run length encoding is enabled or not
     */  
    protected bool $bRunlengthEncoding = false;
    
    /**
     * Constructor
     * 
     * @throws \Exception If the GD extension is not available
     */
    public function __construct(?string $sImagePath = null) {
        if (!extension_loaded('gd')) {
            throw new \Exception('The GD extension is required for SixelConverter');
        }
        
        if ($sImagePath !== null) {
            $this->loadImage($sImagePath);
        }
    }
    
    /**
     * Set the maximum dimensions for the output image
     */
    public function setMaxDimensions(int $iWidth, int $iHeight): self {
        $this->iMaxWidth = max(1, $iWidth);
        $this->iMaxHeight = max(1, $iHeight);
        return $this;
    }
    
    /**
     * Set the number of colors to use in the output
     */
    public function setColorCount(int $iCount): self {
        $this->iColorCount = min(256, max(2, $iCount));
        return $this;
    }

    /**
     * Sets runlength encoding enabled
     */
    public function enableRunlengthEncoding(): self {
        $this->bRunlengthEncoding = true;
        return $this;
    }

    /**
     * Sets runlength encoding disabled (the default)
     */
    public function disableRunlengthEncoding(): self {
        $this->bRunlengthEncoding = false;
        return $this;
    }
    
    /**
     * Load an image from a file path
     * 
     * @throws \Exception If the image cannot be loaded
     */
    public function loadImage(string $sImagePath): self {
        if (!file_exists($sImagePath)) {
            throw new \Exception("Image file not found: $sImagePath");
        }
        
        // Free any existing image resource
        if ($this->rImage !== null) {
            imagedestroy($this->rImage);
            $this->rImage = null;
        }
        
        // Determine image type and load accordingly
        $aImageInfo = getimagesize($sImagePath);
        if ($aImageInfo === false) {
            throw new \Exception("Unable to determine image type: $sImagePath");
        }
        
        [, , $iType] = $aImageInfo;
        
        $this->rImage = match($iType) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($sImagePath),
            IMAGETYPE_PNG => imagecreatefrompng($sImagePath),
            IMAGETYPE_GIF => imagecreatefromgif($sImagePath),
            default => throw new \Exception("Unsupported image format: $iType")
        };
        
        if ($this->rImage === false) {
            throw new \Exception("Failed to load image: $sImagePath");
        }
        
        // Handle transparency for PNG and GIF
        if ($iType == IMAGETYPE_PNG || $iType == IMAGETYPE_GIF) {
            imagealphablending($this->rImage, true);
            imagesavealpha($this->rImage, true);
        }
        
        return $this;
    }
	
    /**
     * Set the image from a binary string containg an image in JPEG/PNG/GIF format
     *
     * @throws \Exception If the image cannot be loaded
     */  	
    public function setImage(string $sImageData): self
    {
        // Free any existing image resource
        if ($this->rImage !== null) {
            imagedestroy($this->rImage);
            $this->rImage = null;
        }

	$this->rImage = imagecreatefromstring($sImageData);
        if ($this->rImage === false) {
            throw new \Exception("Failed to load image from data");
        }
        
        return $this;
    }

    /**
     * Resize the image to fit within the maximum dimensions while preserving aspect ratio
     */
    protected function resizeImage(): \GdImage {
        $iOrigWidth = imagesx($this->rImage);
        $iOrigHeight = imagesy($this->rImage);
        
        // Calculate new dimensions while maintaining aspect ratio
        $fRatio = min($this->iMaxWidth / $iOrigWidth, $this->iMaxHeight / $iOrigHeight);
        $iNewWidth = (int)round($iOrigWidth * $fRatio);
        $iNewHeight = (int)round($iOrigHeight * $fRatio);
        
        // Create resized image
        $rResized = imagecreatetruecolor($iNewWidth, $iNewHeight);
        if ($rResized === false) {
            throw new \RuntimeException('Failed to create image for resizing');
        }
        
        // Preserve transparency
        imagealphablending($rResized, false);
        imagesavealpha($rResized, true);
        
        // Resize with resampling for better quality
        imagecopyresampled(
            $rResized, $this->rImage,
            0, 0, 0, 0,
            $iNewWidth, $iNewHeight, $iOrigWidth, $iOrigHeight
        );
       	imagejpeg($rResized,'/tmp/debug.jpg',100); 
        return $rResized;
    }
    
    /**
     * Quantize colors in the image to reduce the number of colors
     * 
     * @param \GdImage $rImage The GD image resource to quantize
     * @throws \RuntimeException If image creation fails
     */
    protected function quantizeColors(\GdImage $rImage): \GdImage {
        $iWidth = imagesx($rImage);
        $iHeight = imagesy($rImage);
        
        // Create a paletted image
        $rQuantized = imagecreatetruecolor($iWidth, $iHeight);
        if ($rQuantized === false) {
            throw new \RuntimeException('Failed to create image for color quantization');
        }
        
        // Copy the image (so we modify the copy)
        imagecopy($rQuantized, $rImage, 0, 0, 0, 0, $iWidth, $iHeight);
        // Convert to palette image with dithering
        imagetruecolortopalette($rQuantized, true, $this->iColorCount);
        
        
        // Extract the palette
        $this->aPalette = [];
        $iColorCount = min($this->iColorCount, imagecolorstotal($rQuantized));
        for ($i = 0; $i < $iColorCount; $i++) {
            $aColor = imagecolorsforindex($rQuantized, $i);
            $this->aPalette[$i] = [
                'r' => $aColor['red'],
                'g' => $aColor['green'],
                'b' => $aColor['blue'],
            ];
        }
       	imagejpeg($rQuantized,'/tmp/debug2.jpg',100); 
        return $rQuantized;
    }
    
    /**
     * Convert an RGB color to Sixel color definition
     * 
     * @param array{r: int, g: int, b: int} $aColor Associative array with r, g, b components (0-255)
     */
    protected function colorToSixel(array $aColor): string {
        // Scale RGB values from 0-255 to 0-100 range as per Sixel spec
        $iRed = (int)round($aColor['r'] * 100 / 255);
        $iGreen = (int)round($aColor['g'] * 100 / 255);
        $iBlue = (int)round($aColor['b'] * 100 / 255);
       
 	//The first param is the colour model (2 means rgb as %) 
        return "2;{$iRed};{$iGreen};{$iBlue}";
    }
    
    /**
     * Encode a 6-bit value into a Sixel character
     */
    protected function encodeSixelCharacter(int $iValue): string {
        // Sixel characters start at ASCII 63 ('?')
        return chr(63 + ($iValue & 0x3F));
    }

    /**
     * Takes a single row palette entry looks for repeated pixel patterns, and returns
     * a line that has those repeated patterns replaced with a run length encode version
     */ 	
    protected function runLengthEncodeRow(string $sRow): string {
	if(!$this->bRunlengthEncoding){
          return $sRow;
	}
	$sReturn = "";
	$sLast = "";
	$iCount = 0;

	//Step over the string char by char 
	for($i=0; $i<strlen($sRow); $i++){
	  $sCurrent = $sRow[$i];

	  // If the char has changed 
	  if($sCurrent != $sLast){
 	    switch($iCount){
	      case 0:
	        break;
	      case 1: 
	        $sReturn .= $sLast;
	        break;
	      case 2:
	        $sReturn .= $sLast.$sLast;
	        break;
	      case 3:
	        $sReturn .= $sLast.$sLast.$sLast;
	        break;
	      default:
	        //At 4 or more repitions we either break even, or win with run length enconding 
	        $sReturn .= '!'.$iCount.$sLast;
	    }
	    $iCount=0;
	  }
	  $iCount++;
	  $sLast = $sCurrent;
	}
	return  $sReturn;
    }
    
    /**
     * Improved version of toSixel() with proper pixel to Sixel encoding
     * 
     * @throws \Exception If no image is loaded
     */
    public function convertToSixel(): string {
        if ($this->rImage === null) {
            throw new \Exception("No image loaded");
        }
        
        // Resize image to fit within maximum dimensions
        $rResized = $this->resizeImage();
        
        // Quantize colors
        $rQuantized = $this->quantizeColors($rResized);
        
        $iWidth = imagesx($rQuantized);
        $iHeight = imagesy($rQuantized);
        
        // Round height up to multiple of 6
        $iPaddedHeight = (int)ceil($iHeight / 6) * 6;
        
        // Start Sixel output
        $sSixel = "\033P0;0;0q";  // ESC P q - Enter Sixel mode
        
        // Define the color palette
        foreach ($this->aPalette as $iIndex => $aColor) {
            $sSixel .= "#$iIndex;" . $this->colorToSixel($aColor);
        }

	// Create and empty dataset for each palette entry
	$aBlankDataBlock = [];
	$aBlankHasPixel = [];
	for ($iColorIndex = 0; $iColorIndex < count($this->aPalette); $iColorIndex++) {
		$aBlankDataBlock[$iColorIndex] = str_pad("",$iWidth,"?");
		$aBlankHasPixel[$iColorIndex] = false;
	}

        // Process image data
        for ($iY = 0; $iY < $iPaddedHeight; $iY += 6){
		//Reset the row data
		$aCurretRowData = $aBlankDataBlock;
		$aCurretRowHasPixel = $aBlankHasPixel;
                // For each column
                for ($iX = 0; $iX < $iWidth; $iX++) {
		    $aBuffer = [];
                    
                    // Check 6 vertical pixels
                    for ($i = 0; $i < 6; $i++) {
                        $iYPos = $iY + $i;

                        //Add the bit pattern for each palette index, we have encounterd  
                        if ($iYPos < $iHeight) {
                            $iPixel = imagecolorat($rQuantized, $iX, $iYPos);
			    //Mark the palette entry for this row as having data 
                            $aCurretRowHasPixel[$iPixel] = true;
			    if(!array_key_exists($iPixel,$aBuffer)){
				$aBuffer[$iPixel] = 0;
			    }
                            // Set the corresponding bit in the 6-bit value
                            $aBuffer[$iPixel] |= (1 << $i);
                        }
                    }
                    foreach($aBuffer as $iPaletteId => $iValue){
                    	$aCurretRowData[$iPaletteId] = substr_replace($aCurretRowData[$iPaletteId],$this->encodeSixelCharacter($iValue),$iX,1);
		    }
       		}         
                // Only output color data if there are pixels of this color in the current strip
		foreach ($this->aPalette as $iIndex => $aColor) {
			if($aCurretRowHasPixel[$iIndex]){
				 $sSixel .= "#$iIndex" .$this->runLengthEncodeRow($aCurretRowData[$iIndex]).'$';
			}
                }

            
            // As this outputs one line per colour in the pallet, all the entries must end in '$' so each line over prints the previous entry. Expect the line for the final line which must end in '-' which tells the terminal to
            // begin a new on-screen line, with out over printing.  Other wise any image will only ever be 6pixels high on screen, as everthing would over print on the same line. As a pallet entry may not have any output for a given
            // on-screen line we don't know in advnace will per pallet line will be the last one, for the given on-screen line, hence having to replace the last char. 
            $sSixel = substr($sSixel,0, strlen($sSixel)-1)."-";
	
        }
       	// Remove the lash char, that indicates what todo with the next line (as there is no next line)
       	$sSixel = substr($sSixel,0, strlen($sSixel)-1); 

        // End Sixel output
        $sSixel .= "\033\\";  // ESC \ - Exit Sixel mode
        
        // Clean up
        imagedestroy($rResized);
        imagedestroy($rQuantized);
        
        return $sSixel;
    }
    
    /**
     * Destructor - Clean up resources
     */
    public function __destruct() {
        if ($this->rImage !== null) {
            imagedestroy($this->rImage);
            $this->rImage = null;
        }
    }
}
