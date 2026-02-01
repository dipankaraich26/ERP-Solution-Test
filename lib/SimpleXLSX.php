<?php
/**
 * SimpleXLSX - Simple XLSX Reader
 * Lightweight class to read Excel XLSX files without dependencies
 * Based on SimpleXLSX by Sergey Shuchkin
 * @license MIT
 */

class SimpleXLSX {
    private $sharedStrings = [];
    private $sheets = [];
    private $sheetNames = [];
    private $error = null;

    public static function parse($filePath) {
        $xlsx = new self();
        if (!$xlsx->load($filePath)) {
            return false;
        }
        return $xlsx;
    }

    public static function parseFile($filePath) {
        return self::parse($filePath);
    }

    public function error() {
        return $this->error;
    }

    public function rows($sheetIndex = 0) {
        if (isset($this->sheets[$sheetIndex])) {
            return $this->sheets[$sheetIndex];
        }
        return [];
    }

    public function sheetNames() {
        return $this->sheetNames;
    }

    private function load($filePath) {
        if (!file_exists($filePath)) {
            $this->error = 'File not found: ' . $filePath;
            return false;
        }

        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            $this->error = 'Cannot open XLSX file';
            return false;
        }

        // Read shared strings
        $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedStringsXml) {
            $this->parseSharedStrings($sharedStringsXml);
        }

        // Read workbook to get sheet names
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        if ($workbookXml) {
            $this->parseWorkbook($workbookXml);
        }

        // Read worksheets
        $i = 1;
        while (($sheetXml = $zip->getFromName('xl/worksheets/sheet' . $i . '.xml')) !== false) {
            $this->sheets[$i - 1] = $this->parseSheet($sheetXml);
            $i++;
        }

        $zip->close();

        if (empty($this->sheets)) {
            $this->error = 'No worksheets found in XLSX file';
            return false;
        }

        return true;
    }

    private function parseSharedStrings($xml) {
        $doc = new DOMDocument();
        $doc->loadXML($xml);

        $siElements = $doc->getElementsByTagName('si');
        foreach ($siElements as $si) {
            $text = '';
            $tElements = $si->getElementsByTagName('t');
            foreach ($tElements as $t) {
                $text .= $t->nodeValue;
            }
            $this->sharedStrings[] = $text;
        }
    }

    private function parseWorkbook($xml) {
        $doc = new DOMDocument();
        $doc->loadXML($xml);

        $sheets = $doc->getElementsByTagName('sheet');
        foreach ($sheets as $sheet) {
            $this->sheetNames[] = $sheet->getAttribute('name');
        }
    }

    private function parseSheet($xml) {
        $doc = new DOMDocument();
        $doc->loadXML($xml);

        $rows = [];
        $rowElements = $doc->getElementsByTagName('row');

        foreach ($rowElements as $rowElement) {
            $rowNum = (int) $rowElement->getAttribute('r');
            $row = [];

            $cells = $rowElement->getElementsByTagName('c');
            foreach ($cells as $cell) {
                $cellRef = $cell->getAttribute('r');
                $colIndex = $this->getColIndex($cellRef);
                $type = $cell->getAttribute('t');
                $value = '';

                $vElements = $cell->getElementsByTagName('v');
                if ($vElements->length > 0) {
                    $value = $vElements->item(0)->nodeValue;
                }

                // Handle inline strings
                $isElements = $cell->getElementsByTagName('is');
                if ($isElements->length > 0) {
                    $tElements = $isElements->item(0)->getElementsByTagName('t');
                    if ($tElements->length > 0) {
                        $value = $tElements->item(0)->nodeValue;
                    }
                }

                // Convert shared string reference to actual value
                if ($type === 's' && isset($this->sharedStrings[(int)$value])) {
                    $value = $this->sharedStrings[(int)$value];
                }

                // Fill in any missing columns with empty strings
                while (count($row) < $colIndex) {
                    $row[] = '';
                }

                $row[$colIndex] = $value;
            }

            // Add row with proper index
            while (count($rows) < $rowNum - 1) {
                $rows[] = [];
            }
            $rows[$rowNum - 1] = $row;
        }

        return $rows;
    }

    private function getColIndex($cellRef) {
        preg_match('/^([A-Z]+)/', $cellRef, $matches);
        $colLetter = $matches[1];
        $index = 0;
        $len = strlen($colLetter);

        for ($i = 0; $i < $len; $i++) {
            $index = $index * 26 + (ord($colLetter[$i]) - 64);
        }

        return $index - 1;
    }
}
