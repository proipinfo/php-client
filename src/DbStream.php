<?php

declare(strict_types=1);

namespace ProIPInfo;

/**
 * DbStream internal class for db client.
 */
class DbStream
{
    /** @psalm-var resource|closed-resource */
    private $fp;
    private bool $readOnly;

    /**
     * @throws \ProIPException
     */
    public function __construct(string $fname, bool $readOnly)
    {
        $this->readOnly = $readOnly;
        $this->init($fname);
    }

    /**
     * @throws ProIPException
     */
    public function write(string $buf): ?int
    {
        if (!is_resource($this->fp)) {
            throw new ProIPException('Writing to closed file');
        }
        $this->checkWrite();

        return fwrite($this->fp, $buf);
    }

    /**
     *  @throws ProIPException
     */
    public function read(int $count): ?string
    {
        if (!is_resource($this->fp)) {
            throw new ProIPException('Reading from closed file');
        }

        return fread($this->fp, $count);
    }

    /**
     *  @throws ProIPException
     */
    public function tell(): int
    {
        if (!is_resource($this->fp)) {
            throw new ProIPException('Reading from closed file');
        }

        return ftell($this->fp);
    }

    /**
     *  @throws ProIPException
     */
    public function seek(int $pos, int $whence = SEEK_SET): int
    {
        if (!is_resource($this->fp)) {
            throw new ProIPException('Reading from closed file');
        }

        return fseek($this->fp, $pos, $whence);
    }

    public function close(): void
    {
        if (!is_resource($this->fp)) {
            return;
        }
        fclose($this->fp);
    }

    /**
     * @throws ProIPException
     */
    protected function checkWrite(): void
    {
        if ($this->readOnly) {
            throw new ProIPException('File is read-only');
        }
    }

    /**
     * @throws ProIPException
     */
    private function init(string $fname): void
    {
        $this->fp = fopen($fname, ($this->readOnly ? 'rb' : 'w+b'));
        if (!$this->fp) {
            throw new ProIPException("Failed to open {$fname}");
        }
    }
}
