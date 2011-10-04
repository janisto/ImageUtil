<?php
/**
 * Easy resize, filter and compression class for images.
 *
 * All image manipulations are applied to a temporary image. Only the save() method is permanent.
 *
 * Requires: PHP5 and GD2 library.
 *
 * Optional: jpegtran, pngcrush.
 *
 * Usage:
 *
 * <code>
 * include 'ImageUtil.php';
 * 
 * // an absolute URL or path to file. Can be .jpg, .jpeg, .gif or .png
 * $img = new ImageUtil('images/large/input1.jpg');
 * // set width and height and then render image. Can be .jpg, .jpeg, .gif or .png
 * $img->resize(320, 240)->render('output.jpg');
 *
 * // an absolute URL or path to file. Can be .jpg, .jpeg, .gif or .png
 * $img = new ImageUtil('images/large/input2.jpg');
 * $img->resize(150, 100, 'crop')
 * 	   // add watermark
 * 	   ->watermark('images/large/watermark.png')
 * 	   // save path to file. Can be .jpg, .jpeg, .gif or .png
 * 	   ->save('images/small/output.jpg', 80);
 * </code>
 * 
 * @author	Jani Mikkonen <janisto@php.net>
 * @package ImageUtil
 * @license	public domain
 * @link	https://github.com/janisto/ImageUtil/
 */

/**
 * Main class for ImageUtil
 *
 * @throws Exception
 * @author	Jani Mikkonen <janisto@php.net>
 * @package ImageUtil
 * @license	public domain
 * @link	https://github.com/janisto/ImageUtil/
 */
class ImageUtil
{
	/**
	 * Compression options. Path or false.
	 * @var array
	 */
	private $options = array(
		'jpegtran_path' => false,
		'pngcrush_path' => false,
	);

	/**
	 * Image resource identifier.
	 * @var resource
	 */
	private $image;

	/**
	 * Image width.
	 * @var int
	 */
	private $width;

	/**
	 * Image height.
	 * @var int
	 */
	private $height;

	/**
	 * Calculated optimal image width.
	 * @var int
	 */
	private $optimalWidth;

	/**
	 * Calculated optimal image height.
	 * @var int
	 */
	private $optimalHeight;

	/**
	 * Resized image resource identifier.
	 * @var resource
	 */
	private $imageResized;

	/**
	 * Sharpen image state.
	 * @var bool
	 */
	private $sharpen;

	/**
	 * Save image state.
	 * @var bool
	 */
	private $saveState;

	/**
	 * Constructor.
	 *
	 * @throws Exception
	 * @param string	$file an absolute URL or path to file
	 * @param array		$options an array of options
	 */
	public function __construct($file, array $options = array())
	{
		// Check if exec is disabled
		if(function_exists('exec')) {
			// Try to enable compression by default
			if(is_executable('/usr/bin/jpegtran')) {
				$this->options['jpegtran_path'] = '/usr/bin/jpegtran';
			}
			if(is_executable('/usr/bin/pngcrush')) {
				$this->options['pngcrush_path'] = '/usr/bin/pngcrush';
			}
			foreach ($options as $key => $value) {
				if (array_key_exists($key, $this->options)) {
					// Add option to disable compression
					if($value && is_executable($value)){
						$this->options[$key] = $value;
					} else {
						$this->options[$key] = false;
					}
				}
			}
		}

		$this->image = $this->openFile($file);

		// Get width and height
		$this->width  = imagesx($this->image);
		$this->height = imagesy($this->image);

		// Sharpen resized image by default at save or render
		$this->sharpen = true;
	}
	
	/**
	 * Returns the value of the specified option.
	 *
	 * @param string	$key The name of the option to retrieve
	 * @return mixed	option or false
	 * @access public
	 */
	public function getOption($key)
	{
		return isset($this->options[$key]) ? $this->options[$key] : false;
	}

	/**
	 * Resize the image. Must be called first if resize is required since this will reset all filters for image.
	 *
	 * This method is chainable.
	 *
	 * @param int		$newWidth new width of the image in pixels
	 * @param int		$newHeight new height of the image in pixels
	 * @param string	$option one of the image resize options: exact, portrait, landscape, crop or auto. Defaults to auto.
	 * @return object
	 * @access public
	 */
	public function resize($newWidth, $newHeight, $option='auto')
	{
		// Reset
		if($this->imageResized) {
			imagedestroy($this->imageResized);
		}
		$this->saveState = false;

		// Get optimal width and height - based on $option
		$optionArray = $this->getDimensions((int)$newWidth, (int)$newHeight, $option);
		$this->optimalWidth  = round($optionArray['optimalWidth']);
		$this->optimalHeight = round($optionArray['optimalHeight']);

		// Resample - create image canvas of x, y size
		$this->imageResized = imagecreatetruecolor($this->optimalWidth, $this->optimalHeight);
		if (imagetypes() & IMG_PNG) {
			imagesavealpha($this->imageResized, true);
			imagealphablending($this->imageResized, false);
		}
		imagecopyresampled($this->imageResized, $this->image, 0, 0, 0, 0, $this->optimalWidth, $this->optimalHeight, $this->width, $this->height);

		// if option is 'crop', then crop too
		if ($option == 'crop') {
			$this->cropImage($this->optimalWidth, $this->optimalHeight, $newWidth, $newHeight);
		}

		return $this;
	}

	/**
	 * Set blur.
	 *
	 * This method is chainable.
	 *
	 * @param string 	$type blur type: gaussian or selective
	 * @return object
	 * @access public
	 */
	public function blur($type='gaussian')
	{
		$this->checkImage();
		if($type == 'gaussian') {
			imagefilter($this->imageResized, IMG_FILTER_GAUSSIAN_BLUR);
		} else if($type == 'selective') {
			imagefilter($this->imageResized, IMG_FILTER_SELECTIVE_BLUR);
		}
		return $this;
	}
	
	/**
	 * Set brightness.
	 *
	 * This method is chainable.
	 *
	 * @param int 		$value level of brightness between -255 to 255
	 * @return object
	 * @access public
	 */
	public function brightness($value=-20)
	{
		$this->checkImage();
		$value = max(-255, min($value, 255));
		imagefilter($this->imageResized, IMG_FILTER_BRIGHTNESS, $value);
		return $this;
	}

	/**
	 * Set contrast.
	 *
	 * This method is chainable.
	 *
	 * @param int 		$value level of contrast between -255 to 255
	 * @return object
	 * @access public
	 */
	public function contrast($value=-10)
	{
		$this->checkImage();
		$value = max(-255, min($value, 255));
		imagefilter($this->imageResized, IMG_FILTER_CONTRAST, $value);
		return $this;
	}

	/**
	 * Convert image to greyscale.
	 *
	 * This method is chainable.
	 *
	 * @return object
	 * @access public
	 */
	public function greyscale()
	{
		$this->checkImage();
		imagefilter($this->imageResized, IMG_FILTER_GRAYSCALE);
		return $this;
	}

	/**
	 * Interlace image.
	 *
	 * This method is chainable.
	 *
	 * @return object
	 * @access public
	 */
	public function interlace()
	{
		$this->checkImage();
		$imageX = $this->optimalWidth;
		$imageY = $this->optimalHeight;

		$black = imagecolorallocate($this->imageResized, 0, 0, 0);
		for ($y = 1; $y < $imageY; $y += 2) {
			imageline($this->imageResized, 0, $y, $imageX, $y, $black);
		}
		return $this;
	}

	/**
	 * Set noise.
	 *
	 * This method is chainable.
	 *
	 * @param int 		$value factor between 0 and 255
	 * @return object
	 * @access public
	 */
	public function noise($value=30)
	{
		$this->checkImage();
		$value = max(0, min($value, 255));
		$imageX = $this->optimalWidth;
		$imageY = $this->optimalHeight;
		$rand1 = $value;
		$rand2 = -1 * $value;

		for ($x = 0; $x < $imageX; ++$x) {
			for ($y = 0; $y < $imageY; ++$y) {
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
					$newCol = imagecolorallocate($this->imageResized, $red, $green, $blue);
					imagesetpixel($this->imageResized, $x, $y, $newCol);
				}
			}
		}
		return $this;
	}

	/**
	 * Set pixelate.
	 *
	 * This method is chainable.
	 *
	 * @param int 		$value size of pixels
	 * @return object
	 * @access public
	 */
	public function pixelate($value=10)
	{
		$this->checkImage();
		$blockSize = (int)$value;
		if (version_compare(PHP_VERSION, '5.3.0') >= 0) {
			imagefilter($this->imageResized, IMG_FILTER_PIXELATE, $blockSize, true);
		} else {
			$imageX = $this->optimalWidth;
			$imageY = $this->optimalHeight;

			for ($x = 0; $x < $imageX; $x += $blockSize) {
				for ($y = 0; $y < $imageY; $y += $blockSize) {
					$thisCol = imagecolorat($this->imageResized, $x, $y);
					$newR = 0;
					$newG = 0;
					$newB = 0;
					$colours = array();
					for ($k = $x; $k < $x + $blockSize; ++$k) {
						for ($l = $y; $l < $y + $blockSize; ++$l) {
							if ($k < 0) { $colours[] = $thisCol; continue; }
							if ($k >= $imageX) { $colours[] = $thisCol; continue; }
							if ($l < 0) { $colours[] = $thisCol; continue; }
							if ($l >= $imageY) { $colours[] = $thisCol; continue; }
							$colours[] = imagecolorat($this->imageResized, $k, $l);
						}
					}
					foreach($colours as $colour) {
						$newR += ($colour >> 16) & 0xFF;
						$newG += ($colour >> 8) & 0xFF;
						$newB += $colour & 0xFF;
					}
					$numElems = count($colours);
					$newR = round($newR /= $numElems);
					$newG = round($newG /= $numElems);
					$newB = round($newB /= $numElems);
					$newCol = imagecolorallocate($this->imageResized, $newR, $newG, $newB);
					imagefilledrectangle($this->imageResized, $x, $y, $x + $blockSize - 1, $y + $blockSize - 1, $newCol);
				}
			}
		}
		return $this;
	}

	/**
	 * Set rasterbate.
	 *
	 * This method is chainable.
	 *
	 * @param int 		$value maximum circle size in pixels
	 * @return object
	 * @access public
	 */
	public function rasterbate($value=6)
	{
		$this->checkImage();
		$blockSize = (int)$value;
		$imageX = $this->optimalWidth;
		$imageY = $this->optimalHeight;
		$origImage = $this->imageResized;

		$this->imageResized = imagecreatetruecolor($imageX, $imageY);
		$background = imagecolorallocate($this->imageResized, 255, 255, 255);
		imagefill($this->imageResized, 0, 0, $background);

		for ($x = 0; $x < $imageX; $x += $blockSize) {
			for ($y = 0; $y < $imageY; $y += $blockSize) {
				$thisCol = imagecolorat($origImage, $x, $y);
				$newR = 0;
				$newG = 0;
				$newB = 0;
				$colours = array();
				for ($k = $x; $k < $x + $blockSize; ++$k) {
					for ($l = $y; $l < $y + $blockSize; ++$l) {
						if ($k < 0) { $colours[] = $thisCol; continue; }
						if ($k >= $imageX) { $colours[] = $thisCol; continue; }
						if ($l < 0) { $colours[] = $thisCol; continue; }
						if ($l >= $imageY) { $colours[] = $thisCol; continue; }
						$colours[] = imagecolorat($origImage, $k, $l);
					}
				}
				foreach($colours as $colour) {
					$newR += ($colour >> 16) & 0xFF;
					$newG += ($colour >> 8) & 0xFF;
					$newB += $colour & 0xFF;
				}
				$numElems = count($colours);
				$newR = round($newR /= $numElems);
				$newG = round($newG /= $numElems);
				$newB = round($newB /= $numElems);
				$newCol = imagecolorallocate($this->imageResized, $newR, $newG, $newB);
				$newX = ($x+$blockSize)-($blockSize/2);
				$newY = ($y+$blockSize)-($blockSize/2);
				$newRgb = array($newR, $newG, $newB);
				$hsv = $this->rgb2hsv($newRgb);
				$newSize = round($blockSize * ((100 - $hsv[2]) / 100));
				if($newSize > 0) {
					imagefilledellipse($this->imageResized, $newX, $newY, $newSize, $newSize, $newCol);
				}
			}
		}
		imagedestroy($origImage);
		return $this;
	}

	/**
	 * Rotate image.
	 *
	 * This method is chainable.
	 *
	 * @param mixed		$value rotation angle in degrees between -360 and 360 or random
	 * @param string	$bgColor HEX color of the uncovered zone after the rotation
	 * @return object
	 * @access public
	 */
	public function rotate($value='random', $bgColor='ffffff')
	{
		$this->checkImage();
		if($value == 'random') {
			$value = mt_rand(-6, 6);
		} else {
			$value = max(-360, min($value, 360));
		}
		if($value < 0) {
			$value = 360 + $value;
		}
		if($bgColor == 'alpha') {
			// Experimental. GD2 imagerotate seems to be quite buggy with alpha transparency.
			imagealphablending($this->imageResized, false);
			$color = imagecolorallocatealpha($this->imageResized, 255, 255, 255, 127);
			imagecolortransparent($this->imageResized, $color);
			$this->imageResized = imagerotate($this->imageResized, $value, $color);
			imagesavealpha($this->imageResized, true);
		} else {
			$bgColor = str_replace('#', '', strtoupper($bgColor));
			$color = hexdec($bgColor);
			$this->imageResized = imagerotate($this->imageResized, $value, $color);
		}
		return $this;
	}

	/**
	 * Set scatter.
	 *
	 * This method is chainable.
	 *
	 * @param int 		$value intensity
	 * @return object
	 * @access public
	 */
	public function scatter($value=4)
	{
		$this->checkImage();
		$value = (int)$value;
		$imageX = $this->optimalWidth;
		$imageY = $this->optimalHeight;
		$rand1 = $value;
		$rand2 = -1 * $value;

		for ($x = 0; $x < $imageX; ++$x) {
			for ($y = 0; $y < $imageY; ++$y) {
				$distX = rand($rand2, $rand1);
				$distY = rand($rand2, $rand1);
				if ($x + $distX >= $imageX) continue;
				if ($x + $distX < 0) continue;
				if ($y + $distY >= $imageY) continue;
				if ($y + $distY < 0) continue;
				$oldCol = imagecolorat($this->imageResized, $x, $y);
				$newCol = imagecolorat($this->imageResized, $x + $distX, $y + $distY);
				imagesetpixel($this->imageResized, $x, $y, $newCol);
				imagesetpixel($this->imageResized, $x + $distX, $y + $distY, $oldCol);
			}
		}
		return $this;
	}

	/**
	 * Set sepia.
	 *
	 * This method is chainable.
	 *
	 * @param string 	$rgb comma separated RGB value (e.g. '90, 55, 30')
	 * @param int 		$brightness level of brightness
	 * @return object
	 * @access public
	 */
	public function sepia($rgb='90, 55, 30', $brightness=-30)
	{
		// "94,38,18" seems quite nice, too.
		$this->checkImage();
		imagefilter($this->imageResized, IMG_FILTER_GRAYSCALE);
		imagefilter($this->imageResized, IMG_FILTER_BRIGHTNESS, $brightness);
		$rgb = explode(",", $rgb);
		imagefilter($this->imageResized, IMG_FILTER_COLORIZE, trim($rgb[0]), trim($rgb[1]), trim($rgb[2]));
		return $this;
	}

	/**
	 * Set sharpen.
	 *
	 * @param bool		$value sharpen image automatically at first save with optimal image sharpening detection
	 * @return object
	 * @access public
	 */
	public function sharpen($value=true)
	{
		$this->sharpen = (bool)$value;
		return $this;
	}

	/**
	 * Set smooth.
	 *
	 * This method is chainable.
	 *
	 * @param int 		$value level of smoothness -12 to 12. Values outside of the range -8 to 8 are usually unusable.
	 * @return object
	 * @access public
	 */
	public function smooth($value=6)
	{
		$this->checkImage();
		$value = max(-12, min($value, 12));
		imagefilter($this->imageResized, IMG_FILTER_SMOOTH, $value);
		return $this;
	}

	/**
	 * Add watermark to the image.
	 *
	 * This method is chainable.
	 *
	 * @throws Exception
	 * @param string	$file an absolute URL or path to file
	 * @param int		$watermarkTransparency watermark transparency (0-100)
	 * @param int		$padding watermark padding in pixels
	 * @param string	$position position of watermark (one of: TL, TR, BL or BR)
	 * @return object
	 * @access public
	 */
	public function watermark($file, $watermarkTransparency=40, $padding=2, $position='BR')
	{
		$this->checkImage();
		$watermark = $this->openFile($file);
		$watermarkWidth = imagesx($watermark);
		$watermarkHeight = imagesy($watermark);
		$watermarkTransparency = max(0, min($watermarkTransparency, 100));
		$padding = (int)$padding;

		switch(strtoupper($position)) {
			case 'TL': // top left
				$destX = $padding;
				$destY = $padding;
				break;
			case 'TR': // top right
				$destX = $this->optimalWidth - $watermarkWidth - $padding;
				$destY = $padding;
				break;
			case 'BL': // bottom left
				$destX = $padding;
				$destY = $this->optimalHeight - $watermarkHeight - $padding;
				break;
			default:   // bottom right
				$destX = $this->optimalWidth - $watermarkWidth - $padding;
				$destY = $this->optimalHeight - $watermarkHeight - $padding;
				break;
		}

		$this->imagecopymergeAlpha($this->imageResized, $watermark, $destX, $destY, 0, 0, $watermarkWidth, $watermarkHeight, $watermarkTransparency);
		imagedestroy($watermark);

		return $this;
	}

	/**
	 * Add mask layer to image. You create for example polaroid like images.
	 *
	 * This method is chainable.
	 *
	 * @throws Exception
	 * @param string	$file an absolute URL or path to mask .png file
	 * @param int		$top top position of resized image
	 * @param int		$left left position of resized image
	 * @param string	$bgColor HEX color of the uncovered/transparent zone
	 * @return object
	 * @access public
	 */
	public function mask($file, $top=0, $left=0, $bgColor='')
	{
		$this->checkImage();
		$extension = strtolower(strrchr($file, '.'));
		switch($extension) {
			case '.png':
				$mask = @imagecreatefrompng($file);
				break;
			default:
				throw new Exception('mask image type not allowed');
				break;
		}

		if(!$mask) {
			throw new Exception('mask image file not found');
		}

		$maskWidth = imagesx($mask);
		$maskHeight = imagesy($mask);
		$imageX = (int)$top;
		$imageY = (int)$left;

		$img = imagecreatetruecolor($maskWidth, $maskHeight);
		if($bgColor) {
			$bgColor = str_replace('#', '', strtoupper($bgColor));
			$color = hexdec($bgColor);
		} else {
			$color = imagecolortransparent($img, imagecolorallocatealpha($img, 0, 0, 0, 127));
		}
		imagefill($img, 0, 0, $color);
		imagesavealpha($img, true);
		imagealphablending($img, true);
		imagecopy($img, $this->imageResized, $imageX, $imageY, 0, 0, $this->optimalWidth, $this->optimalHeight);
		imagecopy($img, $mask, 0, 0, 0, 0, $maskWidth, $maskHeight);
		imagedestroy($this->imageResized);
		$this->imageResized = $img;
		imagedestroy($mask);
		
		return $this;
	}

	/**
	 * Save the image.
	 *
	 * @throws Exception
	 * @param string	$path file path and output image file name. Directory must writable.
	 * @param int		$imageQuality image quality: 0-100. Defaults to 80.
	 * @param bool		$destroy destroy image resource identifiers on save
	 * @param int  		$chmod permissions for saved image
	 * @return void
	 * @access public
	 */
	public function save($path, $imageQuality=80, $destroy=true, $chmod=0644)
	{
		$imageQuality = max(0, min($imageQuality, 100));

		// Separate the directory, extension, filename and base filename
		$pathParts = pathinfo($path);
		$dir = $pathParts['dirname'];
		$ext = '.' . $pathParts['extension'];
		$file = $pathParts['basename'];
		$base = basename($file, $ext);

		// Normalize the path
		$dir = str_replace('\\', '/', realpath($dir)).'/';

		if (!is_writable($dir)) {
			throw new Exception('image directory unwritable');
		}

		$savePath = $dir . $file;
		$tmpPath = $dir . $base . '_tmp' . $ext;

		$this->sharpenImage();

		// Scale quality from 0-100 to 0-9
		$scaleQuality = round(($imageQuality/100) * 9);

		// Invert quality setting as 0 is best, not 9
		$invertScaleQuality = 9 - $scaleQuality;

		switch($ext) {
			case '.jpg':
			case '.jpeg':
				if (imagetypes() & IMG_JPG) {
					if($this->getOption('jpegtran_path')) {
						imagejpeg($this->imageResized, $tmpPath, $imageQuality);
						$cmd = $this->getOption('jpegtran_path') . ' -copy none -outfile ' . $savePath . ' -optimize -progressive ' . $tmpPath;
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
				throw new Exception('image type not allowed');
				break;
		}

		if ($chmod !== false) {
			// Set permissions
			chmod($savePath, $chmod);
		}

		$this->saveState = true;

		if($destroy) {
			imagedestroy($this->imageResized);
			$this->imageResized = null;
		}
	}

	/**
	 * Output the image to the browser without saving the image.
	 *
	 * @throws Exception
	 * @param string	$file output image file name
	 * @param int		$imageQuality image quality: 0-100. Defaults to 80
	 * @param bool		$destroy destroy image resource identifiers on save
	 * @return void
	 * @access public
	 */
	public function render($file, $imageQuality=80, $destroy=true)
	{
		$this->sharpenImage();

		$imageQuality = max(0, min($imageQuality, 100));

		// Scale quality from 0-100 to 0-9
		$scaleQuality = round(($imageQuality/100) * 9);

		// Invert quality setting as 0 is best, not 9
		$invertScaleQuality = 9 - $scaleQuality;

		$ext = strtolower(strrchr($file, '.'));
		switch($ext) {
			case '.jpg':
			case '.jpeg':
				if (imagetypes() & IMG_JPG) {
					header('Content-Type: image/jpeg');
					imagejpeg($this->imageResized, NULL, $imageQuality);
				}
				break;
			case '.gif':
				if (imagetypes() & IMG_GIF) {
					header('Content-Type: image/gif');
					imagegif($this->imageResized, NULL);
				}
				break;
			case '.png':
				if (imagetypes() & IMG_PNG) {
					header('Content-Type: image/png');
					imagepng($this->imageResized, NULL, $invertScaleQuality);
				}
				break;
			default:
				throw new Exception('image type not allowed');
				break;
		}

		$this->saveState = true;

		if($destroy) {
			imagedestroy($this->imageResized);
			$this->imageResized = null;
		}
	}

	/**
	 * Returns an image resource identifier on success.
	 *
	 * @throws Exception
	 * @param string	$file an absolute URL or path to file
	 * @return mixed	image resource identifier or false
	 * @access private
	 */
	private function openFile($file)
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
				throw new Exception('image type not allowed');
				break;
		}
		
		if(!$img) {
			throw new Exception('image file not found');
		}

		return $img;
	}

	/**
	 * Crop the image.
	 *
	 * @param int		$optimalWidth optimal width of the image
	 * @param int		$optimalHeight optimal height of the image
	 * @param int		$newWidth new width of the image
	 * @param int		$newHeight new height of the image
	 * @return void
	 * @access private
	 */
	private function cropImage($optimalWidth, $optimalHeight, $newWidth, $newHeight)
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
		imagecopyresampled($this->imageResized, $crop, 0, 0, $cropStartX, $cropStartY, $newWidth, $newHeight, $newWidth, $newHeight);

		$this->optimalWidth  = $newWidth;
		$this->optimalHeight = $newHeight;

		imagedestroy($crop);
	}

	/**
	 * Get dimensions of the image.
	 *
	 * @param int		$newWidth width of the image
	 * @param int		$newHeight height of the image
	 * @param string	$option one of the image resize options: exact, portrait, landscape, crop or auto. Defaults to auto.
	 * @return array
	 * @access private
	 */
	private function getDimensions($newWidth, $newHeight, $option)
	{
		// Defaults to auto
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
			case 'crop':
				$optionArray = $this->getOptimalCrop($newWidth, $newHeight);
				$optimalWidth = $optionArray['optimalWidth'];
				$optimalHeight = $optionArray['optimalHeight'];
				break;
			default:
				$optionArray = $this->getSizeByAuto($newWidth, $newHeight);
				$optimalWidth = $optionArray['optimalWidth'];
				$optimalHeight = $optionArray['optimalHeight'];
				break;
		}

		return array('optimalWidth' => $optimalWidth, 'optimalHeight' => $optimalHeight);
	}

	/**
	 * Get dimensions of the image by fixed height.
	 *
	 * @param int		$newHeight height of the image
	 * @return int
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
	 * @param int		$newWidth width of the image
	 * @return int
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
	 * @param int		$newWidth width of the image
	 * @param int		$newHeight height of the image
	 * @return array
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
		} else { // Image to be resized is a square
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
	 * @param int		$newWidth width of the image
	 * @param int		$newHeight height of the image
	 * @return array
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
	 * Checks if resized image is already created.
	 *
	 * @return void
	 * @access private
	 */
	private function checkImage()
	{
		if(!$this->imageResized) {
			$this->resize($this->width, $this->height);
		}
	}

	/**
	 * Sharpens the image at first save.
	 *
	 * @return void
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
	 * Finds optimal image sharpening.
	 *
	 * Based on two things:
	 * (1) the difference between the original size and the final size
	 * (2) the final size
	 *
	 * @param int		$orig original width
	 * @param int		$final final width
	 * @return int
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
	 * Convert an RGB array into HSV (aka HSB) colour space.
	 *
	 * @param  array 	$rgb RGB array
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
			$h = 0;
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
	 * This is like imagecopymerge, but it will handle alpha channel as well.
	 *
	 * @param resource 	$dstImg destination image link resource
	 * @param resource 	$srcImg source image link resource
	 * @param int		$dstX x-coordinate of destination point
	 * @param int		$dstY y-coordinate of destination point
	 * @param int		$srcX x-coordinate of source point
	 * @param int		$srcY y-coordinate of source point
	 * @param int		$srcW source width
	 * @param int		$srcH source height
	 * @param int		$pct the two images will be merged according to pct which can range from 0 to 100
	 * @return void
	 * @access private
	 */
	private function imagecopymergeAlpha($dstImg, $srcImg, $dstX, $dstY, $srcX, $srcY, $srcW, $srcH, $pct)
	{
		// create a cut resource
		$cut = imagecreatetruecolor($srcW, $srcH);
		// make it transparent
		$color = imagecolortransparent($cut, imagecolorallocatealpha($cut, 0, 0, 0, 127));
		imagefill($cut, 0, 0, $color);
		imagesavealpha($cut, true);
		// copy that section of the background to the cut
		imagecopy($cut, $dstImg, 0, 0, $dstX, $dstY, $srcW, $srcH);
		// place the watermark
		imagecopy($cut, $srcImg, 0, 0, $srcX, $srcY, $srcW, $srcH);
		imagecopymerge($dstImg, $cut, $dstX, $dstY, $srcX, $srcY, $srcW, $srcH, $pct);
		// destroy cut resource
		imagedestroy($cut);
	}
}