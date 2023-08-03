<?php

namespace Done\Subtitles\Code\Converters;

class CsvConverter implements ConverterContract
{
    public static $allowedSeparators = [",", ";", "|", "\t"];

    public function canParseFileContent($file_content)
    {
        $csv = self::csvToArray(trim($file_content));

        $row_count = null;
        foreach ($csv as $rows) {
            $count = count($rows);
            if ($row_count === null) {
                $row_count = $count;
            }
            if ($row_count !== $count) {
                return false; // if not every row has the same column count
            }
        }

        if (!isset($csv[1][0])) {
            return false;
        }
        $cell = $csv[1][0];
        $timestamp = preg_replace(TxtConverter::$time_regexp, '', $cell);
        $only_timestamp_on_first_column = trim($timestamp) === '';
        return count($csv[1]) >= 2 && $only_timestamp_on_first_column; // at least 2 columns: timestamp + text
    }

    /**
     * Converts file's content (.srt) to library's "internal format" (array)
     *
     * @param string $file_content      Content of file that will be converted
     * @return array                    Internal format
     */
    public function fileContentToInternalFormat($file_content)
    {
        $data = self::csvToArray($file_content);
        $data_string  = '';

        $is_start_time = (bool) preg_match(TxtConverter::$time_regexp, $data[1][0]);
        $is_end_time = (bool) preg_match(TxtConverter::$time_regexp, $data[1][1]);

        foreach ($data as $k => $row) {
            $timestamp_found = (bool) preg_match(TxtConverter::$time_regexp, $row[0]);
            if ($k === 0  && $timestamp_found === false) { // heading
                continue;
            }

            // format csv file as a txt file, so TxtConverter would be able to understand it
            if ($is_start_time && $is_end_time) {
                $data_string .= $row[0] . ' ' . $row[1] . "\n"; // start end
                $data_string .= $row[2] . "\n"; // text
            } elseif ($is_start_time) {
                $data_string .= $row[0] . "\n"; // start
                $data_string .= $row[1] . "\n"; // text
            } else {
                $data_string .= $row[0] . "\n"; // text
            }
        }
        return (new TxtConverter)->fileContentToInternalFormat($data_string);
    }

    /**
     * Convert library's "internal format" (array) to file's content
     *
     * @param array $internal_format    Internal format
     * @return string                   Converted file content
     */
    public function internalFormatToFileContent(array $internal_format)
    {
        $data = [['Start', 'End', 'Text']];
        foreach ($internal_format as $k => $block) {
            $start = $block['start'];
            $end = $block['end'];
            $text = implode(" ", $block['lines']);

            $data[] = [$start, $end, $text];
        }

        ob_start();
        $fp = fopen('php://output', 'w');
        foreach ($data as $fields) {
            fputcsv($fp, $fields);
        }
        $file_content = ob_get_clean();
        fclose($fp);

        return $file_content;
    }

    private static function csvToArray($content)
    {
        $fp = fopen("php://temp", 'r+');
        fputs($fp, $content);
        rewind($fp);

        $separator = self::detectSeparator($content);
        $csv = [];
        while ( ($data = fgetcsv($fp, 0, $separator) ) !== false ) {
            $csv[] = $data;
        }
        fclose($fp);

        $csv2 = [];
        foreach ($csv as $row) {
            if (!isset($row[0]) || !isset($row[1])) {
                return [];
            }
            if (trim($row[0]) === '' && trim($row[1]) === '') {
                continue;
            }
            $csv2[] = $row;
        }

        return $csv2;
    }

    private static function detectSeparator($file_content)
    {
        $lines = explode("\n", $file_content);
        $results = [];
        foreach ($lines as $line) {
            foreach (self::$allowedSeparators as $delimiter) {
                $count = count(explode($delimiter, $line));
                if ($count < 2) continue; // delimiter not found in line, minimum 2 cols (timestamp + text)

                if (empty($results[$delimiter])) {
                    $results[$delimiter] = [];
                }
                $results[$delimiter][] = $count;
            }
        }

        foreach ($results as $delimiter => $value) {
            $flipped = array_flip($value);
            $results[$delimiter] = max($flipped);
        }

        arsort($results, SORT_NUMERIC);

        return !empty($results) ? key($results) : self::$allowedSeparators[0];
    }
}
