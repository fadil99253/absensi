<?php

namespace App\Services;

use App\Models\Attendance;
use Illuminate\Support\Collection;
use RuntimeException;
use ZipArchive;

class AttendanceExportService
{
    private const HEADERS = ['Tanggal', 'Waktu', 'NIP', 'Nama', 'Departemen', 'Tipe', 'Status', 'Skor Wajah (%)', 'Latitude', 'Longitude'];

    public function xlsx(Collection $records): string
    {
        $path = tempnam(sys_get_temp_dir(), 'hadirku-report-');

        if ($path === false) {
            throw new RuntimeException('Tidak dapat membuat berkas ekspor sementara.');
        }

        $archive = new ZipArchive();
        $opened = $archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($opened !== true) {
            @unlink($path);
            throw new RuntimeException('Tidak dapat membuat berkas Excel.');
        }

        $archive->addFromString('[Content_Types].xml', $this->contentTypesXml());
        $archive->addFromString('_rels/.rels', $this->rootRelationshipsXml());
        $archive->addFromString('xl/workbook.xml', $this->workbookXml());
        $archive->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelationshipsXml());
        $archive->addFromString('xl/worksheets/sheet1.xml', $this->worksheetXml($records));

        if (! $archive->close()) {
            @unlink($path);
            throw new RuntimeException('Berkas Excel tidak dapat diselesaikan.');
        }

        return $path;
    }

    public function pdf(Collection $records, string $from, string $to): string
    {
        $lines = [
            "HadirKu - Rekap Presensi {$from} s.d. {$to}",
            'Tanggal | Waktu | NIP | Nama | Departemen | Tipe | Status | Skor',
            str_repeat('-', 105),
        ];

        foreach ($records as $record) {
            $lines[] = implode(' | ', [
                $record->date->format('Y-m-d'),
                $record->time,
                $record->employee_id,
                $record->employee?->name ?? 'Karyawan tidak aktif',
                $record->employee?->department ?? '-',
                $record->type,
                $record->status,
                $record->match_score === null ? '-' : number_format((float) $record->match_score, 1).'%',
            ]);
        }

        $pages = array_chunk($lines, 38);
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];
        $pageIds = [];

        foreach ($pages as $index => $pageLines) {
            $pageId = 4 + ($index * 2);
            $streamId = $pageId + 1;
            $pageIds[] = "{$pageId} 0 R";
            $commands = "BT\n/F1 9 Tf\n30 560 Td\n13 TL\n";

            foreach ($pageLines as $line) {
                $plain = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $line);
                $plain = $plain === false ? preg_replace('/[^\x20-\x7E]/', '?', $line) : $plain;
                $plain = substr($plain, 0, 140);
                $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $plain);
                $commands .= "({$escaped}) Tj\nT*\n";
            }

            $commands .= "ET";
            $objects[$pageId] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources << /Font << /F1 3 0 R >> >> /Contents {$streamId} 0 R >>";
            $objects[$streamId] = '<< /Length '.strlen($commands)." >>\nstream\n{$commands}\nendstream";
        }

        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $pageIds).'] /Count '.count($pageIds).' >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$object}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $size = max(array_keys($objects)) + 1;
        $pdf .= "xref\n0 {$size}\n0000000000 65535 f \n";
        for ($id = 1; $id < $size; $id++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$id] ?? 0);
        }
        $pdf .= "trailer\n<< /Size {$size} /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }

    private function worksheetXml(Collection $records): string
    {
        $rows = [self::HEADERS];
        foreach ($records as $record) {
            $rows[] = [
                $record->date->format('Y-m-d'),
                $record->time,
                $record->employee_id,
                $record->employee?->name ?? 'Karyawan tidak aktif',
                $record->employee?->department ?? '-',
                $record->type,
                $record->status,
                $record->match_score === null ? '' : (string) $record->match_score,
                $record->latitude === null ? '' : (string) $record->latitude,
                $record->longitude === null ? '' : (string) $record->longitude,
            ];
        }

        $xmlRows = '';
        foreach ($rows as $rowIndex => $values) {
            $cells = '';
            foreach ($values as $columnIndex => $value) {
                $reference = $this->columnName($columnIndex + 1).($rowIndex + 1);
                $text = htmlspecialchars((string) $value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
                $cells .= '<c r="'.$reference.'" t="inlineStr"><is><t xml:space="preserve">'.$text.'</t></is></c>';
            }
            $xmlRows .= '<row r="'.($rowIndex + 1).'">'.$cells.'</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$xmlRows.'</sheetData></worksheet>';
    }

    private function columnName(int $number): string
    {
        $name = '';
        while ($number > 0) {
            $remainder = ($number - 1) % 26;
            $name = chr(65 + $remainder).$name;
            $number = intdiv($number - 1, 26);
        }

        return $name;
    }

    private function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'</Types>';
    }

    private function rootRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private function workbookXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="Presensi" sheetId="1" r:id="rId1"/></sheets></workbook>';
    }

    private function workbookRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'</Relationships>';
    }
}
