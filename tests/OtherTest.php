<?php

namespace Tests;

use Done\Subtitles\Code\UserException;
use PHPUnit\Framework\TestCase;
use Done\Subtitles\Subtitles;
use Helpers\AdditionalAssertionsTrait;

class OtherTest extends TestCase
{
    use AdditionalAssertionsTrait;

    public function testEndTimeIsBiggerThanStart()
    {
        $this->expectException(UserException::class);

        Subtitles::loadFromString('
1
00:00:02,000 --> 00:00:01,000
a
        ');
    }

    public function testTimesOverlapOver10Seconds()
    {
        $this->expectException(UserException::class);

        Subtitles::loadFromString('
1
00:00:01,000 --> 00:01:40,000
a

2
00:00:20,000 --> 00:01:50,000
b
        ');
    }

    public function testFixesUpTo10SecondsTimeOverlap()
    {
        $actual = Subtitles::loadFromString('
1
00:00:01,000 --> 00:00:02,000
a

2
00:00:01,500 --> 00:00:04,000
b
        ')->getInternalFormat();
        $expected = (new Subtitles())->add(1, 1.5, 'a')->add(1.5, 4, 'b')->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);
    }

    public function testMergeIfStartEquals()
    {
        $actual = Subtitles::loadFromString('
3
00:00:03,000 --> 00:00:04,000
c

2
00:00:02,000 --> 00:00:03,000
b

1
00:00:02,000 --> 00:00:02,500
a
        ')->getInternalFormat();
        $expected = (new Subtitles())->add(2, 3, ['a', 'b'])->add(3, 4, 'c')->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);
    }

    public function testExceptionIfCaptionTooLong()
    {
        $this->expectException(UserException::class);

        Subtitles::loadFromString('
1
00:00:00,000 --> 00:05:01,000
a
        ');
    }

    public function testRemovesEmptySubtitles()
    {
        $actual = Subtitles::loadFromString('
[Script Info]

[Events]
Format: Layer, Start, End, Style, Name, MarginL, MarginR, MarginV, Effect, Text
Dialogue: 0,0:21:33.39,0:23:07.52,Default,,0,0,0,,
Dialogue: 0,0:21:41.41,0:21:44.20,사랑에 애태우며,,0,0,0,,test
        ')->getInternalFormat();
        $expected = (new Subtitles())->add(1301.41, 1304.20, 'test')->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);
    }

    public function testRemoveEmptyLines()
    {
        $actual = Subtitles::loadFromString('
[Script Info]

[Events]
Format: Layer, Start, End, Style, Name, MarginL, MarginR, MarginV, Effect, Text
Dialogue: 0,0:00:01.00,0:00:02.00,Default,,0,0,0,,test\N
Dialogue: 0,0:00:03.00,0:00:04.00,Default,,0,0,0,,test
        ')->getInternalFormat();
        $expected = (new Subtitles())->add(1, 2, 'test')->add(3, 4, 'test')->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);
    }

    public function testDetectUtf16Encoding()
    {
        $actual = Subtitles::loadFromFile('./tests/files/utf16.srt')->getInternalFormat();
        $expected = (new Subtitles())->add(0, 1, 'ترجمه و تنظيم زيرنويس')->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);
    }
}
