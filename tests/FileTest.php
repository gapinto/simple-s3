<?php

use SimpleS3\Helpers\File;

class FileTest extends PHPUnit_Framework_TestCase
{
    /**
     * @test
     */
    public function test_load_file()
    {
        $url = 'https://jsonplaceholder.typicode.com/photos';
        $content = json_decode(File::loadFile($url));

        $this->assertTrue(is_array($content));
        $this->assertCount(5000, $content);
    }

    /**
     * @test
     */
    public function get_file_info()
    {
        $path = '/usr/path/to/file.txt';

        $this->assertEquals(File::getPathInfo($path)['dirname'], '/usr/path/to');
        $this->assertEquals(File::getPathInfo($path)['basename'], 'file.txt');
        $this->assertEquals(File::getPathInfo($path)['extension'], 'txt');
        $this->assertEquals(File::getPathInfo($path)['filename'], 'file');
    }

    /**
     * @test
     */
    public function get_the_basename()
    {
        $this->assertEquals(File::getBaseName('[en-GB][2] hello world'), '[en-GB][2] hello world');
        $this->assertEquals(File::getBaseName('仿宋人笔意.txt'), '仿宋人笔意.txt');
        $this->assertEquals(File::getBaseName('/usr/path/to/[en-GB][2] hello world'), '[en-GB][2] hello world');
        $this->assertEquals(File::getBaseName('/usr/path/to/仿宋人笔意.txt'), '仿宋人笔意.txt');
    }

    /**
     * @test
     */
    public function convert_to_hex_and_back()
    {
        $origs = [
            '[en-GB][2] hello world',
            '仿宋人笔意.txt',
        ];

        foreach ($origs as $orig){
            $converted = File::strToHex($orig);
            $original = File::hexToStr($converted);

            $this->assertEquals($original, $orig);
        }
    }

    /**
     * @test
     */
    public function convert_the_full_path_to_hex_and_back()
    {
        $origs = [
            '[en-GB][2] hello world',
            '仿宋人笔意.txt',
            '/usr/path/to/[en-GB][2] hello world',
            '/usr/path/to/仿宋人笔意.txt',
        ];

        foreach ($origs as $orig){
            $converted = File::getFullPathConvertedToHex($orig);
            $original = File::getFullPathConvertedToStr($converted);

            $this->assertEquals($original, $orig);
        }
    }
}
