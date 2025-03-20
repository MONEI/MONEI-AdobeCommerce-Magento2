<?php

namespace Monei\MoneiPayment\Test\Unit\Util;

/**
 * Class to mock the php://input stream
 */
class PhpStreamWrapper
{
    /**
     * @var string The content to return when reading from the stream
     */
    protected static $data;

    /**
     * @var int Current position in the stream
     */
    protected $position;

    /**
     * @var resource Stream context
     */
    public $context;

    /**
     * Open the stream
     *
     * @param string $path The path to open
     * @param string $mode The mode to open with
     * @param int $options Stream options
     * @param string $opened_path Full path that was opened
     * @return bool
     */
    public function stream_open($path, $mode, $options, &$opened_path)
    {
        $this->position = 0;
        return true;
    }

    /**
     * Read from the stream
     *
     * @param int $count Number of bytes to read
     * @return string
     */
    public function stream_read($count)
    {
        $ret = substr(self::$data, $this->position, $count);
        $this->position += strlen($ret);
        return $ret;
    }

    /**
     * Write to the stream
     *
     * @param string $data Data to write
     * @return int
     */
    public function stream_write($data)
    {
        // For testing, we just return the length of data written
        return strlen($data);
    }

    /**
     * Check if we're at the end of the stream
     *
     * @return bool
     */
    public function stream_eof()
    {
        return $this->position >= strlen(self::$data);
    }

    /**
     * Get information about the stream
     *
     * @return array
     */
    public function stream_stat()
    {
        return [
            'dev' => 0,
            'ino' => 0,
            'mode' => 0,
            'nlink' => 0,
            'uid' => 0,
            'gid' => 0,
            'rdev' => 0,
            'size' => strlen(self::$data),
            'atime' => 0,
            'mtime' => 0,
            'ctime' => 0,
            'blksize' => -1,
            'blocks' => -1
        ];
    }

    /**
     * Set position in the stream
     *
     * @param int $offset Stream offset
     * @param int $whence Type of seeking (SEEK_SET, SEEK_CUR, SEEK_END)
     * @return bool
     */
    public function stream_seek($offset, $whence)
    {
        switch ($whence) {
            case SEEK_SET:
                $this->position = $offset;
                break;
            case SEEK_CUR:
                $this->position += $offset;
                break;
            case SEEK_END:
                $this->position = strlen(self::$data) + $offset;
                break;
            default:
                return false;
        }
        return true;
    }

    /**
     * Get current position in the stream
     *
     * @return int
     */
    public function stream_tell()
    {
        return $this->position;
    }

    /**
     * Set the content for the stream
     *
     * @param string $content Content for the stream
     * @return void
     */
    public static function setContent($content)
    {
        self::$data = $content;
    }
}
