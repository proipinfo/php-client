<?php

namespace proipinfo\ProIPInfo;

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
     * @throws \Exception
     */
    public function __construct(string $fname, bool $readOnly)
    {
        $this->readOnly = $readOnly;
        $this->_init($fname);
    }

    /**
     * @param string $buf
     *
     * @return int|false
     * @throws \Exception
     */
    public function write(string $buf)
    {
        $this->_checkWrite();
        return fwrite($this->fp, $buf);
    }

    /**
     * @param int $count
     *
     * @return string|false
     */
    public function read(int $count)
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
     * @param $pos
     * @param int $whence
     * @return int
     */
    public function seek($pos, $whence = SEEK_SET): int
    {
        return fseek($this->fp, $pos, $whence);
    }

    /**
     * @return bool
     */
    public function close(): bool
    {
        fclose($this->fp);
    }

    /**
     * PRIVATE SECTION
     */

    private $fp;
    private $readOnly;

    /**
     * @param $filename
     * @throws \Exception
     */
    private function _init($filename)
    {
        $this->fp = fopen($filename, ($this->readOnly ? "rb" : "w+b"));
        if (!$this->fp) {
            throw new \Exception("Failed to open {$filename}");
        }
    }

    /**
     * @throws \Exception
     */
    private function _checkWrite()
    {
        if ($this->readOnly) {
            throw new \Exception("Файл открыт для чтения");
        }
    }
}
