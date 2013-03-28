<?php

// Xupdate_ftp excutr function
require_once XUPDATE_TRUST_PATH .'/include/FtpCommonFunc.class.php';
require_once 'File/Archive.php';

class Xupdate_FtpCommonZipArchive extends Xupdate_FtpCommonFunc {

	public function __construct() {
		parent::__construct();
	}


	/**
	 * _unzipFile
	 *
	 * @return	bool
	 **/
	public function _unzipFile($caller)
	{
		// local file name
		$downloadDirPath = realpath($this->Xupdate->params['temp_path']);
		$downloadFilePath = $this->Xupdate->params['temp_path'].'/'.$this->download_file;
		$exploredDirPath = realpath($downloadDirPath.'/'.$this->target_key);
		if (empty($downloadFilePath) ) {
			$this->_set_error_log('getDownloadFilePath not found error in: '.$this->_getDownloadFilePath());
			return false;
		}
		if (! chdir($exploredDirPath) ) {
			$this->_set_error_log('chdir error in: '.$exploredDirPath);
			return false;//chdir error
		}
		
		if (substr($this->download_file, -10) === '.class.php') {
			if (@ copy($this->Xupdate->params['temp_path'].'/'.$this->download_file, $exploredDirPath.'/'.$this->download_file)) {
				$this->exploredPreloadPath = $exploredDirPath;
				return true;
			}
		}
		
		$ret = true;
		$source = File_Archive::read($downloadFilePath . '/');
		$className = 'File_Archive_Reader';
		if ($source instanceof $className) {
			$writer = File_Archive::appender($exploredDirPath);
			if (PEAR::isError($writer)) {
				$source->close();
				//$this->message = $writer->getMessage()
				return false;
			}
			
			$isSafemode = (ini_get('safe_mode') == "1");
			$dirs = array();
			while ($source->next() === true) {
				$inner = $source->getFilename();
				$file = $exploredDirPath . '/' . $inner;
				$stat = $source->getStat();
				
				// skip extract if file already exists.
				if ( is_dir($file)
				  || (is_file($file) && filesize($file) == $stat['size'])) {
					continue;
				}
				
				// make dirctory at first for safe_mode
				if ($isSafemode) {
					$dir = (substr($file, -1) == '/') ? substr($file, 0, -1) : dirname($file);
					while (!isset($dirs[$dir]) && $dir != $exploredDirPath) {
						$dirs[$dir] = true;
						$this->Ftp->localMkdir($dir);
						$this->Ftp->localChmod($dir, 0707);
						$dir = dirname($dir);
					}
				}
			   
				$error = $writer->newFile($inner, $stat);
				if (PEAR::isError($error)) {
					//$this->message = $error->getMessage();
					$ret = false;
					break;
				}

				$error = $source->sendData($writer);
				if (PEAR::isError($error)) {
					//$this->message = $error->getMessage();
					$ret = false;
					break;
				}
			}//end loop
		} else {
			if (PEAR::isError($source)) {
				//$this->message = $source->getMessage();
			}
			return false;
		}
		$writer->close();
		$source->close();
		return $ret;
	}

} // end class

?>