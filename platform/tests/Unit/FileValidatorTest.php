<?php

namespace Tests\Unit;

use App\Domain\Files\FileRejected;
use App\Domain\Files\FileValidator;
use PHPUnit\Framework\TestCase;

class FileValidatorTest extends TestCase
{
    private array $tmp = [];

    protected function tearDown(): void
    {
        array_map('unlink', array_filter($this->tmp, 'is_file'));
    }

    private function file(string $content): string
    {
        $p = tempnam(sys_get_temp_dir(), 'fv');
        file_put_contents($p, $content);

        return $this->tmp[] = $p;
    }

    public static function ooxml(string $ext = 'docx', array $extra = [], ?string $ct = null): string
    {
        $p = tempnam(sys_get_temp_dir(), 'ox');
        $z = new \ZipArchive;
        $z->open($p, \ZipArchive::OVERWRITE);
        $main = ['docx' => 'word/document.xml', 'xlsx' => 'xl/workbook.xml', 'pptx' => 'ppt/presentation.xml'][$ext];
        $z->addFromString('[Content_Types].xml', $ct ?? '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Override PartName="/'.$main.'" ContentType="application/vnd.openxmlformats-officedocument.main+xml"/></Types>');
        $z->addFromString('_rels/.rels', '<Relationships/>');
        $z->addFromString($main, '<doc>salam</doc>');
        foreach ($extra as $name => $content) {
            $z->addFromString($name, $content);
        }
        $z->close();

        return $p;
    }

    private function reject(string $path, string $name, string $reason): void
    {
        try {
            FileValidator::validate($path, $name);
            $this->fail("$name rədd olunmalı idi ($reason)");
        } catch (FileRejected $e) {
            $this->assertSame($reason, $e->reason);
        }
    }

    public function test_valid_types_pass_by_content(): void
    {
        $this->assertSame('pdf', FileValidator::validate($this->file("%PDF-1.4\n1 0 obj<<>>endobj\n%%EOF"), 'cavab.pdf')['ext']);
        $this->assertSame('docx', FileValidator::validate($this->tmp[] = self::ooxml('docx'), 'cavab.docx')['ext']);
        $this->assertSame('xlsx', FileValidator::validate($this->tmp[] = self::ooxml('xlsx'), 'cədvəl.xlsx')['ext']);
        $img = imagecreatetruecolor(4, 4);
        ob_start();
        imagepng($img);
        $png = ob_get_clean();
        $this->assertSame('png', FileValidator::validate($this->file($png), 'şəkil.PNG')['ext']);
        ob_start();
        imagejpeg($img);
        $this->assertSame('jpg', FileValidator::validate($this->file(ob_get_clean()), 'foto.jpg')['ext']);
    }

    public function test_disguised_file_rejected_by_magic_bytes(): void
    {
        $this->reject($this->file("MZ\x90\x00 fake executable"), 'cavab.pdf', 'type');
        $this->reject($this->file('<?php system($_GET[1]);'), 'shell.jpg', 'type');
        $this->reject($this->file('<html><script>alert(1)</script>'), 'x.png', 'type');
        $this->reject($this->file('%PDF-1.4'), 'evil.exe', 'type');
        $this->reject($this->file('hello'), 'cavab.txt', 'type');
        $this->reject($this->tmp[] = self::ooxml('docx'), 'cavab.xlsx', 'type'); // docx məzmunu xlsx adı ilə
    }

    public function test_macro_formats_rejected(): void
    {
        $this->reject($this->tmp[] = self::ooxml('docx'), 'makro.docm', 'macro');
        $this->reject($this->tmp[] = self::ooxml('docx', ['word/vbaProject.bin' => 'VBA']), 'gizli.docx', 'macro');
        $this->reject($this->tmp[] = self::ooxml('xlsx', [], '<Types><Default ContentType="application/vnd.ms-excel.sheet.macroEnabled.main+xml"/></Types>'), 'gizli.xlsx', 'macro');
    }

    public function test_zip_bomb_rejected(): void
    {
        $this->reject($this->tmp[] = self::ooxml('docx', ['word/media/bomb.bin' => str_repeat("\0", 50 * 1024 * 1024)]), 'bomba.docx', 'zip_bomb');
        $many = [];
        for ($i = 0; $i < FileValidator::MAX_ENTRIES + 1; $i++) {
            $many["word/x{$i}.xml"] = 'a';
        }
        $this->reject($this->tmp[] = self::ooxml('docx', $many), 'cox.docx', 'zip_bomb');
    }

    public function test_path_traversal_entries_rejected(): void
    {
        $this->reject($this->tmp[] = self::ooxml('docx', ['../../evil.php' => 'x']), 'trav.docx', 'type');
    }
}
