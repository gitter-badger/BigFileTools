<?php

/**
 * Class for manipulating files bigger than 2GB in PHP
 *
 * @author Honza Kuchar
 * @license LGPL
 * @copyright Copyright (c) 20011, Jan Kuchař
 */
class BigFileTools extends Object {

	/**
	 * File path
	 * @var string
	 */
	protected $path;

	/**
	 * Use in BigFileTools::$mathLib when you want to use BCMath for mathematical operations
	 */
	const MATH_BCMATH = "BCMath";

	/**
	 * Use in BigFileTools::$mathLib when you want to use GMP for mathematical operations
	 */
	const MATH_GMP = "GMP";

	/**
	 * Wich mathematical library use for mathematical operations
	 * @var string (on of constants BigFileTools::MATH_*)
	 */
	public static $mathLib;
	/**
	 * If none of fast mode is available to compute filesize, BigFileTools use to compute size slow
	 * method of reading all file contents. If you want to enable this behaviour,
	 * Turn fastMode to false (default to true)
	 * @var bool
	 */
	public static $fastMode = false;

	/**
	 * Ininialization of class
	 * Do not call directely.
	 */
	static function init() {
		if (function_exists("bcadd")) {
			self::$mathLib = self::MATH_BCMATH;
		} elseif (function_exists("gmp_add")) {
			self::$mathLib = self::MATH_GMP;
		} else {
			throw new InvalidStateException("You must have installed one of there mathemtical libraries: BC Math or GMP!");
		}
	}

	static function fromPath($path) {
		return new self($path);
	}

	/**
	 * Constructor - do not call directelly
	 * @param string $path
	 */
	function __construct($path) {
		if (!file_exists($path) OR !is_file($path)) {
			throw new Exception("File not found at $path");
		}
		$this->path = $path;
	}

	/**
	 * Getts current filepath
	 * @return string
	 */
	function getPath($absolutize = false) {
		if ($absolutize) {
			$this->absolutizePath();
		}
		return $this->path;
	}

	/**
	 * Converts relative path to absolute
	 */
	function absolutizePath() {
		return $this->path = realpath($this->path);
	}

	/**
	 * Moves file to new location
	 * @param string $dest
	 */
	function move($dest) {
		if (move_uploaded_file($this->path, $dest)) {
			$this->path = $dest;
			return TRUE;
		} else {
			@unlink($dest); // needed in PHP < 5.3 & Windows; intentionally @
			if (rename($this->path, $dest)) {
				$this->path = $dest;
				return TRUE;
			} else {
				if (copy($this->path, $dest)) {
					unlink($this->path);
					$this->path = $dest;
					return TRUE;
				}
				return FALSE;
			}
		}
	}

	/**
	 * Changes path of this file object
	 * @param string $dest
	 */
	function relocate($dest) {
		$this->path = $dest;
	}

	/**
	 * Size of file
	 * @return string | float 
	 * @throws InvalidStateException
	 */
	public function size($float = false) {
		if ($float == true) {
			return (float) $this->size(false);
		}

		$return = $this->sizeNativeSeek();
		if ($return) {
			return $return;
		}

		$this->absolutizePath();

		$return = $this->sizeCurl();
		if ($return) {
			return $return;
		}

		$return = $this->sizeExec();
		if ($return) {
			return $return;
		}

		$return = $this->sizeCom();
		if ($return) {
			return $return;
		}

		if (!self::$fastMode) {
			$return = $this->sizeNativeRead();
			if ($return) {
				return $return;
			}
		}

		throw new InvalidStateException("Can not size of file $this->path!");
	}

	// <editor-fold defaultstate="collapsed" desc="size* implementations">
	/**
	 * Returns file size by using native fseek function
	 * @see http://www.php.net/manual/en/function.filesize.php#79023
	 * @see http://www.php.net/manual/en/function.filesize.php#102135
	 * @return string | bool (false when fail)
	 */
	protected function sizeNativeSeek() {
		// This should work for large files on 64bit platforms and for small files every where
		$fp = fopen($this->path, "rb");
		if (!$fp) {
			return false;
		}
		$res = fseek($fp, 0, SEEK_END);
		if ($res === 0) {
			$pos = (string) ftell($fp);
			fclose($fp);
			return $pos;
		} else {
			fclose($fp);
			return false;
		}
	}

	/**
	 * Returns file size by using native fread function
	 * @see http://stackoverflow.com/questions/5501451/php-x86-how-to-get-filesize-of-2gb-file-without-external-program/5504829#5504829
	 * @return string | bool (false when fail)
	 */
	function sizeNativeRead() {
		$fp = fopen($this->path, "rb");
		if (!$fp) {
			return false;
		}

		rewind($fp);
		$offset = PHP_INT_MAX - 1;

		$size = (string) $offset;
		if (fseek($fp, $offset) !== 0) {
			fclose($fp);
			return false;
		}
		$chunksize = 1024 * 1024;
		while (!feof($fp)) {
			$readed = strlen(fread($fp, $chunksize));
			if (self::$mathLib == self::MATH_BCMATH) {
				$size = bcadd($size, $readed);
			} elseif (self::$mathLib == self::MATH_GMP) {
				$size = gmp_add($size, $readed);
			} else {
				throw new InvalidStateException("No mathematical library availabled");
			}
		}
		if (self::$mathLib == self::MATH_GMP) {
			gmp_strval($size);
		}
		fclose($fp);
		return $size;
	}

	/**
	 * Returns file size by using curl module
	 * @see http://www.php.net/manual/en/function.filesize.php#100434
	 * @return string | bool (false when fail or cUrl module not available)
	 */
	protected function sizeCurl() {
		// If program goes here, file must be larger than 2GB
		// curl solution - cross platform and really cool :)
		if (function_exists("curl_init")) {
			$ch = curl_init("file://" . realpath($this->path));
			curl_setopt($ch, CURLOPT_NOBODY, true);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_HEADER, true);
			$data = curl_exec($ch);
			curl_close($ch);
			if ($data !== false && preg_match('/Content-Length: (\d+)/', $data, $matches)) {
				return (string) $matches[1];
			}
		} else {
			return false;
		}
	}

	/**
	 * Returns file size by using external program (exec needed)
	 * @see http://stackoverflow.com/questions/5501451/php-x86-how-to-get-filesize-of-2gb-file-without-external-program/5502328#5502328
	 * @return string | bool (false when fail or exec is disabled)
	 */
	protected function sizeExec() {
		// filesize using exec
		// If the platform is Windows...
		if (function_exists("exec")) {
			$escapedPath = escapeshellarg($this->path);
			if (strtoupper(substr(PHP_OS, 0, 3)) == 'WIN') {
				// Try using the NT substition modifier %~z
				$size = trim(exec("for %F in ($escapedPath) do @echo %~zF"));
				// If the return is blank, zero, or not a number
				if ($size AND ctype_digit($size)) {
					return (string) $size;
				}

				// Otherwise, return the result of the 'for' command
			}

			// If the platform is not Windows, use the stat command (should work for *nix and MacOS)
			return (string) trim(exec("stat -c%s $escapedPath"));
		} else {
			return false;
		}
	}

	/**
	 * Returns file size by using Windows COM interface
	 * @see http://stackoverflow.com/questions/5501451/php-x86-how-to-get-filesize-of-2gb-file-without-external-program/5502328#5502328
	 * @return string | bool (false when fail or COM not available)
	 */
	protected function sizeCom() {
		if (class_exists("COM")) {
			// Use the Windows COM interface
			$fsobj = new COM('Scripting.FileSystemObject');
			if (dirname($this->path) == '.')
				$this->path = ((substr(getcwd(), -1) == DIRECTORY_SEPARATOR) ? getcwd() . basename($this->path) : getcwd() . DIRECTORY_SEPARATOR . basename($this->path));
			$f = $fsobj->GetFile($this->path);
			return (string) $f->Size;
		}
	}

	// </editor-fold>
}

File::init();