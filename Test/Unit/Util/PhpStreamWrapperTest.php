<?php

namespace Monei\MoneiPayment\Test\Unit\Util;

use PHPUnit\Framework\TestCase;

class PhpStreamWrapperTest extends TestCase
{
    /**
     * @var PhpStreamWrapper
     */
    private $wrapper;

    /**
     * Set up test environment
     */
    protected function setUp(): void
    {
        $this->wrapper = new PhpStreamWrapper();
    }

    /**
     * Test setting content
     */
    public function testSetContent(): void
    {
        $testContent = 'Test content';
        PhpStreamWrapper::setContent($testContent);

        // Use reflection to access protected static property
        $reflection = new \ReflectionClass(PhpStreamWrapper::class);
        $property = $reflection->getProperty('data');
        $property->setAccessible(true);

        $this->assertEquals($testContent, $property->getValue());
    }

    /**
     * Test stream_open method
     */
    public function testStreamOpen(): void
    {
        $result = $this->wrapper->stream_open('php://input', 'r', 0, $openedPath);
        $this->assertTrue($result);

        // Check if position was reset to 0
        $reflection = new \ReflectionProperty($this->wrapper, 'position');
        $reflection->setAccessible(true);
        $this->assertEquals(0, $reflection->getValue($this->wrapper));
    }

    /**
     * Test stream_read method
     */
    public function testStreamRead(): void
    {
        $testContent = 'Test content';
        PhpStreamWrapper::setContent($testContent);

        // Set up position
        $reflection = new \ReflectionProperty($this->wrapper, 'position');
        $reflection->setAccessible(true);
        $reflection->setValue($this->wrapper, 0);

        // Read first 4 bytes
        $result1 = $this->wrapper->stream_read(4);
        $this->assertEquals('Test', $result1);
        $this->assertEquals(4, $reflection->getValue($this->wrapper));

        // Read next 8 bytes
        $result2 = $this->wrapper->stream_read(8);
        $this->assertEquals(' content', $result2);
        $this->assertEquals(12, $reflection->getValue($this->wrapper));
    }

    /**
     * Test stream_eof method
     */
    public function testStreamEof(): void
    {
        $testContent = 'Short';
        PhpStreamWrapper::setContent($testContent);

        // Set up position
        $reflection = new \ReflectionProperty($this->wrapper, 'position');
        $reflection->setAccessible(true);

        // Not at EOF
        $reflection->setValue($this->wrapper, 0);
        $this->assertFalse($this->wrapper->stream_eof());

        // At EOF
        $reflection->setValue($this->wrapper, 5);
        $this->assertTrue($this->wrapper->stream_eof());
    }

    /**
     * Test stream_seek method
     */
    public function testStreamSeek(): void
    {
        $testContent = 'Test seeking in stream';
        PhpStreamWrapper::setContent($testContent);

        // Set up position property
        $reflection = new \ReflectionProperty($this->wrapper, 'position');
        $reflection->setAccessible(true);
        $reflection->setValue($this->wrapper, 0);

        // Test SEEK_SET
        $this->wrapper->stream_seek(5, SEEK_SET);
        $this->assertEquals(5, $reflection->getValue($this->wrapper));

        // Test SEEK_CUR
        $this->wrapper->stream_seek(3, SEEK_CUR);
        $this->assertEquals(8, $reflection->getValue($this->wrapper));

        // Test SEEK_END
        $this->wrapper->stream_seek(-6, SEEK_END);
        $this->assertEquals(strlen($testContent) - 6, $reflection->getValue($this->wrapper));

        // Test invalid whence
        $this->assertFalse($this->wrapper->stream_seek(0, 999));
    }

    /**
     * Test stream_tell method
     */
    public function testStreamTell(): void
    {
        // Set up position
        $reflection = new \ReflectionProperty($this->wrapper, 'position');
        $reflection->setAccessible(true);
        $reflection->setValue($this->wrapper, 42);

        $this->assertEquals(42, $this->wrapper->stream_tell());
    }

    /**
     * Test stream_stat method
     */
    public function testStreamStat(): void
    {
        $testContent = 'Test content';
        PhpStreamWrapper::setContent($testContent);

        $stat = $this->wrapper->stream_stat();

        $this->assertIsArray($stat);
        $this->assertEquals(strlen($testContent), $stat['size']);
    }

    /**
     * Test stream_write method
     */
    public function testStreamWrite(): void
    {
        $testString = 'Test write';
        $result = $this->wrapper->stream_write($testString);

        $this->assertEquals(strlen($testString), $result);
    }
}
