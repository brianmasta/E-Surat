<?php

namespace App\Support;

use App\Models\AppSetting;
use App\Models\Letter;
use ZipArchive;

class OutgoingLetterDocx
{
    public static function make(Letter $letter): string
    {
        $agency = AppSetting::agency();
        $tmp = tempnam(sys_get_temp_dir(), 'surat-keluar-');
        $docxPath = $tmp.'.docx';
        rename($tmp, $docxPath);

        $zip = new ZipArchive;
        $zip->open($docxPath, ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', self::contentTypes());
        $zip->addFromString('_rels/.rels', self::rels());
        $zip->addFromString('word/_rels/document.xml.rels', self::documentRels());
        $zip->addFromString('word/styles.xml', self::styles());
        $zip->addFromString('word/document.xml', self::document($letter, $agency));
        $zip->close();

        return $docxPath;
    }

    private static function document(Letter $letter, array $agency): string
    {
        $paragraphs = collect(preg_split("/\r\n|\n|\r/", (string) $letter->outgoing_body))
            ->filter(fn (string $line) => trim($line) !== '')
            ->map(fn (string $line) => self::paragraph($line))
            ->implode('');

        $date = $letter->letter_date?->translatedFormat('d F Y') ?? now()->translatedFormat('d F Y');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    '.self::center(self::xml($agency['name'] ?? 'Instansi Pemerintah'), true).'
    '.self::center(self::xml($agency['unit'] ?? 'Unit Kerja'), true).'
    '.self::center(self::xml($agency['address'] ?? ''), false).'
    '.self::horizontalLine().'
    '.self::paragraph('Nomor: '.$letter->number).'
    '.self::paragraph('Sifat: '.$letter->nature).'
    '.self::paragraph('Perihal: '.$letter->subject).'
    '.self::paragraph('Tujuan: '.$letter->external_party).'
    '.self::paragraph('').'
    '.self::paragraph('Yth. '.$letter->external_party).'
    '.self::paragraph('di tempat').'
    '.$paragraphs.'
    '.self::paragraph('').'
    '.self::right(($agency['city'] ?? 'Tempat').', '.$date).'
    '.self::right($letter->signer_title ?: 'Pimpinan').'
    '.self::paragraph('').self::paragraph('').self::paragraph('').'
    '.self::right($letter->signer_name ?: 'Nama Pejabat').'
    <w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440"/></w:sectPr>
  </w:body>
</w:document>';
    }

    private static function paragraph(string $text): string
    {
        return '<w:p><w:r><w:t xml:space="preserve">'.self::xml($text).'</w:t></w:r></w:p>';
    }

    private static function center(string $text, bool $bold): string
    {
        $boldTag = $bold ? '<w:b/>' : '';

        return '<w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:rPr>'.$boldTag.'</w:rPr><w:t xml:space="preserve">'.$text.'</w:t></w:r></w:p>';
    }

    private static function right(string $text): string
    {
        return '<w:p><w:pPr><w:jc w:val="right"/></w:pPr><w:r><w:t xml:space="preserve">'.self::xml($text).'</w:t></w:r></w:p>';
    }

    private static function horizontalLine(): string
    {
        return '<w:p><w:pPr><w:pBdr><w:bottom w:val="single" w:sz="8" w:space="1"/></w:pBdr></w:pPr></w:p>';
    }

    private static function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    private static function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
</Types>';
    }

    private static function rels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>';
    }

    private static function documentRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"></Relationships>';
    }

    private static function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:style w:type="paragraph" w:default="1" w:styleId="Normal">
    <w:name w:val="Normal"/>
    <w:rPr><w:rFonts w:ascii="Times New Roman" w:hAnsi="Times New Roman"/><w:sz w:val="24"/></w:rPr>
  </w:style>
</w:styles>';
    }
}
