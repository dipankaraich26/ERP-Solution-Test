<?php
/**
 * SimpleXLSXGen - Simple XLSX Generator
 * Lightweight class to generate Excel XLSX files without dependencies
 * Based on SimpleXLSXGen by Sergey Shuchkin
 * @license MIT
 */

class SimpleXLSXGen {
    private $rows = [];
    private $curSheet = 0;
    private $sheets = [['name' => 'Sheet1', 'rows' => []]];
    private $defaultFont = 'Calibri';
    private $defaultFontSize = 11;

    public static function fromArray(array $rows, $sheetName = null) {
        $xlsx = new self();
        if ($sheetName) {
            $xlsx->sheets[$xlsx->curSheet]['name'] = $sheetName;
        }
        foreach ($rows as $row) {
            $xlsx->addRow($row);
        }
        return $xlsx;
    }

    public function addRow(array $row) {
        $this->sheets[$this->curSheet]['rows'][] = $row;
        return $this;
    }

    public function downloadAs($filename) {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
        header('Cache-Control: max-age=0');
        echo $this->generate();
        exit;
    }

    public function generate() {
        $zip = new ZipArchive();
        $tempFile = tempnam(sys_get_temp_dir(), 'xlsx');

        if ($zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new Exception('Cannot create XLSX file');
        }

        // [Content_Types].xml
        $zip->addFromString('[Content_Types].xml', $this->contentTypes());

        // _rels/.rels
        $zip->addFromString('_rels/.rels', $this->rels());

        // xl/_rels/workbook.xml.rels
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRels());

        // xl/workbook.xml
        $zip->addFromString('xl/workbook.xml', $this->workbook());

        // xl/styles.xml
        $zip->addFromString('xl/styles.xml', $this->styles());

        // xl/sharedStrings.xml and xl/worksheets/sheet1.xml
        $sharedStrings = [];
        $sheetXml = $this->sheet($this->sheets[0]['rows'], $sharedStrings);
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
        $zip->addFromString('xl/sharedStrings.xml', $this->sharedStrings($sharedStrings));

        $zip->close();

        $content = file_get_contents($tempFile);
        unlink($tempFile);

        return $content;
    }

    private function contentTypes() {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
    <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
    <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
    <Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>
</Types>';
    }

    private function rels() {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>';
    }

    private function workbookRels() {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
    <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
    <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>
</Relationships>';
    }

    private function workbook() {
        $name = htmlspecialchars($this->sheets[0]['name']);
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <sheets>
        <sheet name="' . $name . '" sheetId="1" r:id="rId1"/>
    </sheets>
</workbook>';
    }

    private function styles() {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <fonts count="2">
        <font>
            <sz val="' . $this->defaultFontSize . '"/>
            <name val="' . $this->defaultFont . '"/>
        </font>
        <font>
            <b/>
            <sz val="' . $this->defaultFontSize . '"/>
            <name val="' . $this->defaultFont . '"/>
        </font>
    </fonts>
    <fills count="2">
        <fill><patternFill patternType="none"/></fill>
        <fill><patternFill patternType="gray125"/></fill>
    </fills>
    <borders count="1">
        <border><left/><right/><top/><bottom/><diagonal/></border>
    </borders>
    <cellStyleXfs count="1">
        <xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
    </cellStyleXfs>
    <cellXfs count="2">
        <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
        <xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>
    </cellXfs>
</styleSheet>';
    }

    private function sheet(array $rows, &$sharedStrings) {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <sheetData>';

        $rowNum = 1;
        foreach ($rows as $row) {
            $xml .= '<row r="' . $rowNum . '">';
            $colNum = 0;
            foreach ($row as $value) {
                $colLetter = $this->getColLetter($colNum);
                $cellRef = $colLetter . $rowNum;

                if (is_numeric($value)) {
                    $xml .= '<c r="' . $cellRef . '"><v>' . $value . '</v></c>';
                } else {
                    $value = (string) $value;
                    if (!isset($sharedStrings[$value])) {
                        $sharedStrings[$value] = count($sharedStrings);
                    }
                    $styleId = ($rowNum === 1) ? ' s="1"' : '';
                    $xml .= '<c r="' . $cellRef . '" t="s"' . $styleId . '><v>' . $sharedStrings[$value] . '</v></c>';
                }
                $colNum++;
            }
            $xml .= '</row>';
            $rowNum++;
        }

        $xml .= '</sheetData></worksheet>';
        return $xml;
    }

    private function sharedStrings(array $strings) {
        $count = count($strings);
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . $count . '" uniqueCount="' . $count . '">';

        foreach ($strings as $string => $index) {
            $xml .= '<si><t>' . htmlspecialchars($string) . '</t></si>';
        }

        $xml .= '</sst>';
        return $xml;
    }

    private function getColLetter($num) {
        $letter = '';
        while ($num >= 0) {
            $letter = chr(65 + ($num % 26)) . $letter;
            $num = intval($num / 26) - 1;
        }
        return $letter;
    }
}
