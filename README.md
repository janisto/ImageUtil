ImageUtil
=========

Easy resize, filter and compression class for images.

Usage
-----

	include 'ImageUtil.php';
	$resize = new ImageUtil('path/to/image.jpg');
	// an absolute URL or path to file. Can be .jpg, .jpeg, .gif or .png
	$resize->resizeImage(320, 240);
	// width and height
	$resize->saveImage('path/to/output_1.jpg');
	// path to file. Can be .jpg, .jpeg, .gif or .png

Examples
--------

Examples can be found here: [http://www.mikkonen.info/imageutil/](http://www.mikkonen.info/imageutil/)

