<?php

namespace Tests\Formats;

use Done\Subtitles\Code\Converters\DfxpConverter;
use Done\Subtitles\Code\Helpers;
use Done\Subtitles\Subtitles;
use PHPUnit\Framework\TestCase;
use Helpers\AdditionalAssertionsTrait;

class DfxpTest extends TestCase {

    use AdditionalAssertionsTrait;

    public function testRecognizesDfxp()
    {
        $content = file_get_contents('./tests/files/dfxp.dfxp');
        $converter = Helpers::getConverterByFileContent($content);
        $this->assertTrue($converter::class === DfxpConverter::class);
    }

    public function testConvertFromSrtToDfxp()
    {
        $srt_path = './tests/files/srt.srt';
        $dfxp_path = './tests/files/dfxp.dfxp';
        $temporary_dfxp_path = './tests/files/tmp/dfxp.dfxp';

        @unlink($temporary_dfxp_path);

        // srt to stl
        Subtitles::convert($srt_path, $temporary_dfxp_path);
        $this->assertFileEqualsIgnoringLineEndings($dfxp_path, $temporary_dfxp_path);

        unlink($temporary_dfxp_path);
    }

    public function testConvertFromDfxpToSrt()
    {
        $srt_path = './tests/files/srt.srt';
        $dfxp_path = './tests/files/dfxp.dfxp';

        $dfxp_object = Subtitles::loadFromFile($dfxp_path);
        $actual = $dfxp_object->getInternalFormat();

        $srt_object = Subtitles::loadFromFile($srt_path);
        $expected = $srt_object->getInternalFormat();

        $this->assertInternalFormatsEqual($expected, $actual);
    }

    public function testParsesDifferentBr()
    {
        $dfxp_object = Subtitles::loadFromFile('./tests/files/dfxp_with_different_br.dfxp');
        $actual = $dfxp_object->getInternalFormat();
        $expected = (new Subtitles())->add(0, 1, ['one', 'two', 'three'])->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);
    }

}