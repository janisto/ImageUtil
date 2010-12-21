<?php
/**
 * Easy resize, filter and compression class for images.
 *
 * Requires: Requires PHP5, GD library.
 * Optional: jpegtran, pngcrush.
 * Examples:
 *
 * include 'ImageUtil.php';
 * $options = array(
 *   'jpegtran_path' => '/path/to/jpegtran',
 *   'pngcrush_path' => '/path/to/pngcrush',
 * );
 * $resize = new ImageUtil('images/large/input.jpg', $options);
 * $resize->resizeImage(150, 100, 'crop');
 * $resize->setSepia();
 * $resize->addWatermark('images/large/watermark.png', 40);
 * $resize->saveImage('images/small/output.jpg', 80);
 *
 * $resize = new ImageUtil('images/large/input2.jpg');
 * $resize->resizeImage(320, 240);
 * $resize->saveImage('images/small/output2.jpg');
 *
 * @author Jani Mikkonen <janisto@php.net>
 * @link   https://github.com/janisto/ImageUtil/
 */
 
class ImageUtil
{
	private $options = array(
		'jpegtran_path' => false,
		'pngcrush_path' => false,
	);
	private $image;
	private $width;
	private $height;
	private $optimalWidth;
	private $optimalHeight;
	private $imageResized;
	private $watermark;
	private $polaroid;
	private $greyscale;
	private $brightness;
	private $contrast;
	private $smooth;
	private $blur;
	private $sepia;
	private $interlace;
	private $scatter;
	private $pixelate;
	private $noise;
	private $rasterbate;
	private $sharpen;
	private $saveState;

	/**
	 * Constructor.
	 *
	 * @param string	$file an absolute URL or path to file
	 * @param array		$options an array of options
	 * @access public
	 */
	function __construct($file, array $options = array())
	{
		foreach ($options as $key => $value) {
			if (array_key_exists($key, $this->options) && is_executable($value)) {
				$this->options[$key] = $value;
			}
		}
		
		// Open up the file
		$this->image = $this->openImage($file);

		// Get width and height
		if($this->image) {
			$this->width  = imagesx($this->image);
			$this->height = imagesy($this->image);
		}
		
		// Sharpen resized image by default
		$this->sharpen = true;
		
	}
	
	/**
	 * Returns the value of the specified option.
	 *
	 * @param string	$key The name of the option to retrieve
	 * @return mixed
	 * @access public
	 */
	public function getOption($key)
	{
		return isset($this->options[$key]) ? $this->options[$key] : false;
	}

	/**
	 * Returns an image resource identifier on success, false on errors.
	 *
	 * @param string	$file an absolute URL or path to file
	 * @return mixed
	 * @access private
	 */
	private function openImage($file)
	{
		$extension = strtolower(strrchr($file, '.'));
		switch($extension) {
			case '.jpg':
			case '.jpeg':
				$img = @imagecreatefromjpeg($file);
				break;
			case '.gif':
				$img = @imagecreatefromgif($file);
				break;
			case '.png':
				$img = @imagecreatefrompng($file);
				break;
			default:
				$img = false;
				break;
		}
		return $img;
	}

	/**
	 * Resize the image.
	 *
	 * @param int		$newWidth new width of the image in pixels
	 * @param int		$newHeight new height of the image in pixels
	 * @param string	$option image resize option
	 * @return mixed
	 * @access public
	 */
	public function resizeImage($newWidth, $newHeight, $option="auto")
	{
		if(!$this->image) {
			return false;
		}
		
		// Get optimal width and height - based on $option
		$optionArray = $this->getDimensions((int) $newWidth, (int) $newHeight, $option);
		$this->optimalWidth  = round($optionArray['optimalWidth']);
		$this->optimalHeight = round($optionArray['optimalHeight']);

		// Resample - create image canvas of x, y size
		$this->imageResized = imagecreatetruecolor($this->optimalWidth, $this->optimalHeight);
		if (imagetypes() & IMG_PNG) {
			imagesavealpha($this->imageResized, true);
			imagealphablending($this->imageResized, false);
		}
		imagecopyresampled($this->imageResized, $this->image, 0, 0, 0, 0, $this->optimalWidth, $this->optimalHeight, $this->width, $this->height);

		// reset
		$this->saveState = false;
		$this->greyscale = false;
		$this->brightness = false;
		
		// if option is 'crop', then crop too
		if ($option == 'crop') {
			$this->crop($this->optimalWidth, $this->optimalHeight, $newWidth, $newHeight);
		}
	}

	/**
	 * Crop the image.
	 *
	 * @access private
	 */
	private function crop($optimalWidth, $optimalHeight, $newWidth, $newHeight)
	{

		// Find center - this will be used for the crop
		$cropStartX = round(( $optimalWidth / 2) - ( $newWidth /2 ));
		$cropStartY = round(( $optimalHeight/ 2) - ( $newHeight/2 ));

		$crop = $this->imageResized;

		// Now crop from center to exact requested size
		$this->imageResized = imagecreatetruecolor($newWidth , $newHeight);
		if (imagetypes() & IMG_PNG) {
			imagesavealpha($this->imageResized, true);
			imagealphablending($this->imageResized, false);
		}
		imagecopyresampled($this->imageResized, $crop , 0, 0, $cropStartX, $cropStartY, $newWidth, $newHeight , $newWidth, $newHeight);
		
		$this->optimalWidth  = $newWidth;
		$this->optimalHeight = $newHeight;
		
		imagedestroy($crop);
	}

	/**
	 * Get dimensions of the image.
	 *
	 * @access private
	 */
	private function getDimensions($newWidth, $newHeight, $option)
	{
		switch ($option) {
			case 'exact':
				$optimalWidth = $newWidth;
				$optimalHeight= $newHeight;
				break;
			case 'portrait':
				$optimalWidth = $this->getSizeByFixedHeight($newHeight);
				$optimalHeight= $newHeight;
				break;
			case 'landscape':
				$optimalWidth = $newWidth;
				$optimalHeight= $this->getSizeByFixedWidth($newWidth);
				break;
			case 'auto':
				$optionArray = $this->getSizeByAuto($newWidth, $newHeight);
				$optimalWidth = $optionArray['optimalWidth'];
				$optimalHeight = $optionArray['optimalHeight'];
				break;
			case 'crop':
				$optionArray = $this->getOptimalCrop($newWidth, $newHeight);
				$optimalWidth = $optionArray['optimalWidth'];
				$optimalHeight = $optionArray['optimalHeight'];
				break;
		}
		return array('optimalWidth' => $optimalWidth, 'optimalHeight' => $optimalHeight);
	}

	/**
	 * Get dimensions of the image by fixed height.
	 *
	 * @access private
	 */
	private function getSizeByFixedHeight($newHeight)
	{
		$ratio = $this->width / $this->height;
		$newWidth = $newHeight * $ratio;
		return $newWidth;
	}

	/**
	 * Get dimensions of the image by fixed width.
	 *
	 * @access private
	 */
	private function getSizeByFixedWidth($newWidth)
	{
		$ratio = $this->height / $this->width;
		$newHeight = $newWidth * $ratio;
		return $newHeight;
	}

	/**
	 * Get dimensions of the image automatically.
	 *
	 * @access private
	 */
	private function getSizeByAuto($newWidth, $newHeight)
	{
		if ($this->height < $this->width) { // Image to be resized is wider (landscape)
			$optimalWidth = $newWidth;
			$optimalHeight= $this->getSizeByFixedWidth($newWidth);
		} elseif ($this->height > $this->width) { // Image to be resized is taller (portrait)
			$optimalWidth = $this->getSizeByFixedHeight($newHeight);
			$optimalHeight= $newHeight;
		} else { // Image to be resizerd is a square
			if ($newHeight < $newWidth) {
				$optimalWidth = $newWidth;
				$optimalHeight= $this->getSizeByFixedWidth($newWidth);
			} else if ($newHeight > $newWidth) {
				$optimalWidth = $this->getSizeByFixedHeight($newHeight);
				$optimalHeight= $newHeight;
			} else { // Square being resized to a square
				$optimalWidth = $newWidth;
				$optimalHeight= $newHeight;
			}
		}
		return array('optimalWidth' => $optimalWidth, 'optimalHeight' => $optimalHeight);
	}

	/**
	 * Get optimal crop of the image.
	 *
	 * @access private
	 */
	private function getOptimalCrop($newWidth, $newHeight)
	{
		$heightRatio = $this->height / $newHeight;
		$widthRatio  = $this->width /  $newWidth;
		if ($heightRatio < $widthRatio) {
			$optimalRatio = $heightRatio;
		} else {
			$optimalRatio = $widthRatio;
		}
		$optimalHeight = $this->height / $optimalRatio;
		$optimalWidth  = $this->width  / $optimalRatio;
		return array('optimalWidth' => $optimalWidth, 'optimalHeight' => $optimalHeight);
	}

	/**
	 * Resize the image.
	 *
	 * @param string	$savePath image save path
	 * @param int		$imageQuality image quality
	 * @param bool		$destroy destroy image resource identifiers on save
	 * @access public
	 */
	public function saveImage($savePath, $imageQuality="80", $destroy=true)
	{
		$this->sharpenImage();

		// Scale quality from 0-100 to 0-9
		$scaleQuality = round(($imageQuality/100) * 9);

		// Invert quality setting as 0 is best, not 9
		$invertScaleQuality = 9 - $scaleQuality;
		
		$extension = strtolower(strrchr($savePath, '.'));
		switch($extension) {
			case '.jpg':
			case '.jpeg':
				if (imagetypes() & IMG_JPG) {
					if($this->getOption('jpegtran_path')) {
						$tmpPath = $this->getTmpPath($savePath);
						imagejpeg($this->imageResized, $tmpPath, $imageQuality);
						$cmd = $this->getOption('jpegtran_path') . ' -copy none -optimize -progressive ' . $tmpPath . ' ' . $savePath;
						exec($cmd);
						@unlink($tmpPath);
					} else {
						imagejpeg($this->imageResized, $savePath, $imageQuality);
					}
				}
				break;
			case '.gif':
				if (imagetypes() & IMG_GIF) {
					imagegif($this->imageResized, $savePath);
				}
				break;
			case '.png':
				if (imagetypes() & IMG_PNG) {
					if($this->getOption('pngcrush_path')) {
						$tmpPath = $this->getTmpPath($savePath);
						imagepng($this->imageResized, $tmpPath, $invertScaleQuality);
						$cmd = $this->getOption('pngcrush_path') . ' -q ' . $tmpPath . ' ' . $savePath;
						exec($cmd);
						@unlink($tmpPath);
					} else {
						imagepng($this->imageResized, $savePath, $invertScaleQuality);
					}
				}
				break;
			default:
				break;
		}
		
		$this->saveState = true;
		
		if($destroy) {
			imagedestroy($this->imageResized);
			if($this->watermark) {
				imagedestroy($this->watermark);
			}
			if($this->polaroid) {
				imagedestroy($this->polaroid);
			}
		}
	}

	/**
	 * Add watermark to the image.
	 *
	 * @param string	$file an absolute URL or path to file
	 * @param int		$watermarkTransparency watermark transparency (0-100)
	 * @param int		$padding watermark padding in pixels
	 * @param string	$position position of watermark (TL, TR, BL or BR)
	 * @return mixed
	 * @access public
	 */
	public function addWatermark($file, $watermarkTransparency="40", $padding="2", $position="BR")
	{
		$this->checkImage();
		$extension = strtolower(strrchr($file, '.'));
		switch($extension) {
			case '.jpg':
			case '.jpeg':
				$this->watermark = @imagecreatefromjpeg($file);
				break;
			case '.gif':
				$this->watermark = @imagecreatefromgif($file);
				break;
			case '.png':
				$this->watermark = @imagecreatefrompng($file);
				break;
			default:
				return false;
				break;
		}
		
		$wm_width = imagesx($this->watermark);
		$wm_height = imagesy($this->watermark);
		$padding = (int) $padding;
		
		switch(strtoupper($position)) {
			case 'TL': // top left
				$dest_x = $padding;
				$dest_y = $padding;
				break;
			case 'TR': // top right
				$dest_x = $this->optimalWidth - $wm_width - $padding;
				$dest_y = $padding;
				break;
			case 'BL': // bottom left
				$dest_x = $padding;
				$dest_y = $this->optimalHeight - $wm_height - $padding;
				break;
			default:   // bottom right
				$dest_x = $this->optimalWidth - $wm_width - $padding;
				$dest_y = $this->optimalHeight - $wm_height - $padding;
				break;
		}
		
		$this->imagecopymergeAlpha($this->imageResized, $this->watermark, $dest_x, $dest_y, 0, 0, $wm_width, $wm_height, (int) $watermarkTransparency);
	}

	/**
	 * Create polaroid like image.
	 *
	 * @param string	$file an absolute URL or path to mask .png file
	 * @param int		$top top position of resized image
	 * @param int		$left left position of resized image
	 * @param string	$bgcolor HEX color of the uncovered/transparent zone
	 * @return mixed
	 * @access public
	 */
	public function createPolaroid($file, $top="0", $left="0", $bgcolor="")
	{
		$this->checkImage();
		$extension = strtolower(strrchr($file, '.'));
		switch($extension) {
			case '.png':
				$this->polaroid = @imagecreatefrompng($file);
				break;
			default:
				return false;
				break;
		}

		$p_width = imagesx($this->polaroid);
		$p_height = imagesy($this->polaroid);
		$image_x = (int) $top;
		$image_y = (int) $left;
		
		$img = imagecreatetruecolor($p_width, $p_height);
		if($bgcolor) {
			$bgcolor = str_replace("#", "", strtoupper($bgcolor));
			$color = hexdec($bgcolor);
		} else {
			$color = imagecolortransparent($img, imagecolorallocatealpha($img, 0, 0, 0, 127));
		}
		imagefill($img, 0, 0, $color);

		imagesavealpha($img, true);
		imagealphablending($img, true);
		
		imagecopy($img, $this->imageResized, $image_x, $image_y, 0, 0, $this->optimalWidth, $this->optimalHeight);
		imagecopy($img, $this->polaroid, 0, 0, 0, 0, $p_width, $p_height);

		imagedestroy($this->imageResized);
		$this->imageResized = $img;
	}

	/**
	 * Set greyscale.
	 *
	 * @param bool
	 * @access public
	 */
	public function setGreyscale($value=true)
	{
		$this->checkImage();
		$this->greyscale = $value;
		imagefilter($this->imageResized, IMG_FILTER_GRAYSCALE);
	}

	/**
	 * Set brightness.
	 *
	 * @param int $value level of brightness
	 * @access public
	 */
	public function setBrightness($value="-20")
	{
		$this->checkImage();
		$this->brightness = $value;
		imagefilter($this->imageResized, IMG_FILTER_BRIGHTNESS, $this->brightness);
	}

	/**
	 * Set contrast.
	 *
	 * @param int $value level of contrast
	 * @access public
	 */
	public function setContrast($value="-10")
	{
		$this->checkImage();
		$this->contrast = $value;
		imagefilter($this->imageResized, IMG_FILTER_CONTRAST, $this->contrast);
	}

	/**
	 * Set smooth.
	 *
	 * @param int $value level of smoothness
	 * @access public
	 */
	public function setSmooth($value="6")
	{
		// You're not likely to want values outside of the range -8 to 8.
		$this->checkImage();
		$this->smooth = $value;
		imagefilter($this->imageResized, IMG_FILTER_SMOOTH, $this->smooth);
	}

	/**
	 * Set blur.
	 *
	 * @param string $type blur type: gaussian or selective.
	 * @access public
	 */
	public function setBlur($type="gaussian")
	{
		$this->checkImage();
		$this->blur = $type;
		if($this->blur == "gaussian") {
			imagefilter($this->imageResized, IMG_FILTER_GAUSSIAN_BLUR);
		} else if($this->blur == "selective") {
			imagefilter($this->imageResized, IMG_FILTER_SELECTIVE_BLUR);
		}
	}

	/**
	 * Set sepia.
	 *
	 * @param string $rgb RGB value
	 * @access public
	 */
	public function setSepia($rgb="90, 55, 30")
	{
		// "94,38,18" seems quite nice, too.
		$this->checkImage();
		$this->sepia = $rgb;
		if(!$this->greyscale) {
			imagefilter($this->imageResized, IMG_FILTER_GRAYSCALE);
		}
		if(!$this->brightness) {
			imagefilter($this->imageResized, IMG_FILTER_BRIGHTNESS, "-30");
		}
		$rgb = explode(",", $this->sepia);
		imagefilter($this->imageResized, IMG_FILTER_COLORIZE, trim($rgb[0]), trim($rgb[1]), trim($rgb[2]));
	}

	/**
	 * Set interlace.
	 *
	 * @param bool
	 * @access public
	 */
	public function setInterlace($value=true)
	{
		$this->checkImage();
		$this->interlace = $value;
		$this->interlaceImage();
	}

	/**
	 * Set scatter.
	 *
	 * @param int $value intensity
	 * @access public
	 */
	public function setScatter($value="4")
	{
		$this->checkImage();
		$this->scatter = $value;
		$this->scatterImage();
	}

	/**
	 * Set pixelate.
	 *
	 * @param int $value size of pixels
	 * @access public
	 */
	public function setPixelate($value="10")
	{
		$this->checkImage();
		$this->pixelate = $value;
		$this->pixelateImage();
	}

	/**
	 * Set noise.
	 *
	 * @param int $value factor between 0 and 255
	 * @access public
	 */
	public function setNoise($value="30")
	{
		$this->checkImage();
		$this->noise = $value;
		$this->addNoise();
	}

	/**
	 * Set rasterbate.
	 *
	 * @param int $value maximum circle size in pixels
	 * @access public
	 */
	public function setRasterbate($value="6")
	{
		$this->checkImage();
		$this->rasterbate = $value;
		$this->rasterbateImage();
	}
	
	/**
	 * Set sharpen.
	 *
	 * @param bool
	 * @access public
	 */
	public function setSharpen($value=true)
	{
		$this->sharpen = $value;
	}

	/**
	 * Rotate image.
	 *
	 * @param mixed		$value rotation angle in degrees between -360 and 360 or auto
	 * @param string	$bgcolor HEX color of the uncovered zone after the rotation
	 * @access public
	 */
	public function rotate($value="auto", $bgcolor="ffffff")
	{
		$this->checkImage();
		if($value == "auto") {
			$value = mt_rand(-6, 6);
		}
		if($value < -360 || $value > 360) {
			$value = 0;
		}
		if($value < 0) {
			$value = 360 + $value;
		}
		if($bgcolor == "alpha") {
			// Experimental. GD imagerotate seems to be quite buggy with alpha transparency.
			imagealphablending($this->imageResized, false);
			$color = imagecolorallocatealpha($this->imageResized, 255, 255, 255, 127);
			imagecolortransparent($this->imageResized, $color);
			$this->imageResized = imagerotate($this->imageResized, $value, $color);
			imagesavealpha($this->imageResized, true);
		} else {
			$bgcolor = str_replace("#", "", strtoupper($bgcolor));
			$color = hexdec($bgcolor);
			$this->imageResized = imagerotate($this->imageResized, $value, $color);
		}
	}
	
	/**
	 * This is like imagecopymerge, but it will handle alpha channel as well.
	 *
	 * @access private
	 */
	private function imagecopymergeAlpha($dst_im, $src_im, $dst_x, $dst_y, $src_x, $src_y, $src_w, $src_h, $pct)
	{
		// create a cut resource
		$cut = imagecreatetruecolor($src_w, $src_h);
		// make it transparent
		$color = imagecolortransparent($cut, imagecolorallocatealpha($cut, 0, 0, 0, 127));
		imagefill($cut, 0, 0, $color);
		imagesavealpha($cut, true);
		// copy that section of the background to the cut
		imagecopy($cut, $dst_im, 0, 0, $dst_x, $dst_y, $src_w, $src_h);
		// place the watermark
		imagecopy($cut, $src_im, 0, 0, $src_x, $src_y, $src_w, $src_h);
		imagecopymerge($dst_im, $cut, $dst_x, $dst_y, $src_x, $src_y, $src_w, $src_h, $pct);
	}

	/**
	 * Checks if resized image is already created.
	 *
	 * @access private
	 */
	private function checkImage()
	{
		if(!$this->imageResized) {
			$this->resizeImage($this->width, $this->height);
		}
	}

	/**
	 * Returns temporary name/path for the image.
	 * 
	 * @return mixed
	 * @access private
	 */
	private function getTmpPath($path) {
		$extension = strtolower(strrchr($path, '.'));
		$dirname = dirname($path);
		if($dirname) {
			$dirname .= '/';
		}
		switch($extension) {
			case '.jpg':
				$basename = basename($path, ".jpg");
				$tmpname = $basename . '_tmp.jpg';
				break;
			case '.jpeg':
				$basename = basename($path, ".jpeg");
				$tmpname = $basename . '_tmp.jpeg';
				break;
			case '.gif':
				$basename = basename($path, ".gif");
				$tmpname = $basename . '_tmp.gif';
				break;
			case '.png':
				$basename = basename($path, ".png");
				$tmpname = $basename . '_tmp.png';
				break;
			default:
				return false;
				break;
		}
		return $dirname . $tmpname;
	}

	/**
	 * Finds optimal image sharpenin.
	 *
	 * Based on two things:
	 * (1) the difference between the original size and the final size
	 * (2) the final size
	 *
	 * @access private
	 */
	private function findSharp($orig, $final)
	{
		$final  = $final * (750.0 / $orig);
		$a      = 52;
		$b      = -0.27810650887573124;
		$c      = .00047337278106508946;
		$result = $a + $b * $final + $c * $final * $final;
		return max(round($result), 0);
	}

	/**
	 * Sharpens the image with first save.
	 *
	 * @access private
	 */
	private function sharpenImage()
	{
		$this->checkImage();

		if(!$this->saveState && $this->sharpen == true) {
			$sharpness = $this->findSharp($this->width, $this->optimalWidth);
			$sharpenMatrix = array(
				array(-1, -2, -1),
				array(-2, $sharpness + 12, -2),
				array(-1, -2, -1)
			);
			$divisor = $sharpness;
			$offset  = 0;
			imageconvolution($this->imageResized, $sharpenMatrix, $divisor, $offset);
		}
	}

	/**
	 * Interlaces the image.
	 *
	 * @access private
	 */
	private function interlaceImage()
	{
		$imagex = $this->optimalWidth;
		$imagey = $this->optimalHeight;

		$black = imagecolorallocate($this->imageResized, 0, 0, 0);
		for ($y = 1; $y < $imagey; $y += 2) {
			imageline($this->imageResized, 0, $y, $imagex, $y, $black);
		}
	}

	/**
	 * Scatters the image.
	 *
	 * @access private
	 */
	private function scatterImage()
	{
		$imagex = $this->optimalWidth;
		$imagey = $this->optimalHeight;
		$rand1 = $this->scatter;
		$rand2 = -1 * $this->scatter;

		for ($x = 0; $x < $imagex; ++$x) {
			for ($y = 0; $y < $imagey; ++$y) {
				$distx = rand($rand2, $rand1);
				$disty = rand($rand2, $rand1);
				if ($x + $distx >= $imagex) continue;
				if ($x + $distx < 0) continue;
				if ($y + $disty >= $imagey) continue;
				if ($y + $disty < 0) continue;
				$oldcol = imagecolorat($this->imageResized, $x, $y);
				$newcol = imagecolorat($this->imageResized, $x + $distx, $y + $disty);
				imagesetpixel($this->imageResized, $x, $y, $newcol);
				imagesetpixel($this->imageResized, $x + $distx, $y + $disty, $oldcol);
			}
		}
	}

	/**
	 * Pixelates the image.
	 *
	 * @access private
	 */
	private function pixelateImage()
	{
		if (version_compare(PHP_VERSION, '5.3.0') >= 0) {
			imagefilter($this->imageResized, IMG_FILTER_PIXELATE, $this->pixelate, true);
		} else {
			
			$imagex = $this->optimalWidth;
			$imagey = $this->optimalHeight;
			$blocksize = $this->pixelate;

			for ($x = 0; $x < $imagex; $x += $blocksize) {
				for ($y = 0; $y < $imagey; $y += $blocksize) {
					$thiscol = imagecolorat($this->imageResized, $x, $y);
					$newr = 0;
					$newg = 0;
					$newb = 0;
					$colours = array();
					for ($k = $x; $k < $x + $blocksize; ++$k) {
						for ($l = $y; $l < $y + $blocksize; ++$l) {
							if ($k < 0) { $colours[] = $thiscol; continue; }
							if ($k >= $imagex) { $colours[] = $thiscol; continue; }
							if ($l < 0) { $colours[] = $thiscol; continue; }
							if ($l >= $imagey) { $colours[] = $thiscol; continue; }
							$colours[] = imagecolorat($this->imageResized, $k, $l);
						}
					}
					foreach($colours as $colour) {
						$newr += ($colour >> 16) & 0xFF;
						$newg += ($colour >> 8) & 0xFF;
						$newb += $colour & 0xFF;
					}
					$numelements = count($colours);
					$newr = round($newr /= $numelements);
					$newg = round($newg /= $numelements);
					$newb = round($newb /= $numelements);
					$newcol = imagecolorallocate($this->imageResized, $newr, $newg, $newb);
					imagefilledrectangle($this->imageResized, $x, $y, $x + $blocksize - 1, $y + $blocksize - 1, $newcol);
				}
			}
		}
	}

	/**
	 * Adds noise to the image.
	 *
	 * @access private
	 */
	private function addNoise() {
		$imagex = $this->optimalWidth;
		$imagey = $this->optimalHeight;
		$rand1 = $this->noise;
		$rand2 = -1 * $this->noise;
		
		for ($x = 0; $x < $imagex; ++$x) {
			for ($y = 0; $y < $imagey; ++$y) {
				if (rand(0,1)) {
					$rgb = imagecolorat($this->imageResized, $x, $y);
					$red = ($rgb >> 16) & 0xFF;
					$green = ($rgb >> 8) & 0xFF;
					$blue = $rgb & 0xFF;
					$modifier = rand($rand2, $rand1);
					$red += $modifier;
					$green += $modifier;
					$blue += $modifier;
					if ($red > 255) $red = 255;
					if ($green > 255) $green = 255;
					if ($blue > 255) $blue = 255;
					if ($red < 0) $red = 0;
					if ($green < 0) $green = 0;
					if ($blue < 0) $blue = 0;
					$newcol = imagecolorallocate($this->imageResized, $red, $green, $blue);
					imagesetpixel($this->imageResized, $x, $y, $newcol);
				}
			}
		}
	}

	/**
	 * Convert an RGB array into HSV (aka HSB) colour space.
	 *
	 * @param  array $rgb
	 * @return array
	 * @access private
	 */
	private function rgb2hsv($rgb){
		$r = $rgb[0];
		$g = $rgb[1];
		$b = $rgb[2];

		$minVal = min($r, $g, $b);
		$maxVal = max($r, $g, $b);
		$delta  = $maxVal - $minVal;
		$v = $maxVal / 255;

		if ($delta == 0) {
			$h = 0;
			$s = 0;
		} else {
			$s = $delta / $maxVal;
			$delR = ((($maxVal - $r) / 6) + ($delta / 2)) / $delta;
			$delG = ((($maxVal - $g) / 6) + ($delta / 2)) / $delta;
			$delB = ((($maxVal - $b) / 6) + ($delta / 2)) / $delta;
			if ($r == $maxVal){
				$h = $delB - $delG;
			} else if ($g == $maxVal) {
				$h = (1 / 3) + $delR - $delB;
			} else if ($b == $maxVal) {
				$h = (2 / 3) + $delG - $delR;
			}
			if ($h < 0) {
				$h++;
			}
			if ($h > 1) {
				$h--;
			}
		}
		$h = round($h * 360);
		$s = round($s * 100);
		$v = round($v * 100);
		return array($h, $s, $v);
	}
	
	/**
	 * Rasterbates the image.
	 *
	 * @access private
	 */
	private function rasterbateImage()
	{
		$imagex = $this->optimalWidth;
		$imagey = $this->optimalHeight;
		$blocksize = $this->rasterbate;
		$origImage = $this->imageResized;

		$this->imageResized = imagecreatetruecolor($imagex, $imagey);
		$background = imagecolorallocate($this->imageResized, 255, 255, 255);
		imagefill($this->imageResized, 0, 0, $background);

		for ($x = 0; $x < $imagex; $x += $blocksize) {
			for ($y = 0; $y < $imagey; $y += $blocksize) {
				$thiscol = imagecolorat($origImage, $x, $y);
				$newr = 0;
				$newg = 0;
				$newb = 0;
				$colours = array();
				for ($k = $x; $k < $x + $blocksize; ++$k) {
					for ($l = $y; $l < $y + $blocksize; ++$l) {
						if ($k < 0) { $colours[] = $thiscol; continue; }
						if ($k >= $imagex) { $colours[] = $thiscol; continue; }
						if ($l < 0) { $colours[] = $thiscol; continue; }
						if ($l >= $imagey) { $colours[] = $thiscol; continue; }
						$colours[] = imagecolorat($origImage, $k, $l);
					}
				}
				foreach($colours as $colour) {
					$newr += ($colour >> 16) & 0xFF;
					$newg += ($colour >> 8) & 0xFF;
					$newb += $colour & 0xFF;
				}
				$numelements = count($colours);
				$newr = round($newr /= $numelements);
				$newg = round($newg /= $numelements);
				$newb = round($newb /= $numelements);
				$newcol = imagecolorallocate($this->imageResized, $newr, $newg, $newb);
				$newx = ($x+$blocksize)-($blocksize/2);
				$newy = ($y+$blocksize)-($blocksize/2);
				$newrgb = array($newr, $newg, $newb);
				$hsv = $this->rgb2hsv($newrgb);
				$newsize = round($blocksize * ((100 - $hsv[2]) / 100));
				if($newsize > 0) {
					imagefilledellipse($this->imageResized, $newx, $newy, $newsize, $newsize, $newcol);
				}
			}
		}
	}

}
?>