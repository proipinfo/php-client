<?php
namespace ProIPInfo;

/**
 * DbStream internal class for db client
 */
class DbStream
{
    /**
     * PUBLIC SECTION
     */

    /**
     * @param string $fname
     * @param bool $readOnly
     */
    public function __construct($fname, $readOnly)
    {
        $this->readOnly = $readOnly;
        $this->_init($fname);
    }
    
    /**
     * @param string $buf
     *
     * @return int|false
     */
    public function write($buf)
    {
        $this->_checkWrite();
        return fwrite($this->fp, $buf);
    }
    
    /**
     * @param int $count
     *
     * @return string|false
     */
    public function read($count)
    {
        return fread($this->fp, $count);
    }
    
    /**
     * @return int|false
     */
    public function tell()
    {
        return ftell($this->fp);
    }
    
    /**
     * @param int $pos
     * @param  $whence
     *
     * @return int
     */
    public function seek($pos, $whence = SEEK_SET)
    {
        return fseek($this->fp, $pos, $whence);
    }
    
    /**
     * @return bool
     */
    public function close()
    {
        fclose($this->fp);
    }

    /**
     * PRIVATE SECTION
     */

    private $fp;
    private $readOnly;
    
    private function _init($filename)
    {
        $this->fp = fopen($filename, ($this->readOnly? "rb" : "w+b"));
        if (!$this->fp) {
            throw new \Exception("Failed to open {$filename}");
        }
    }

    private function _checkWrite()
    {
        if ($this->readOnly) {
            throw new \Exception("Файл открыт для чтения");
        }
    }
}
