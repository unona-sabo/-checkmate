<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory as SpreadsheetIOFactory;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use Smalot\PdfParser\Parser as PdfParser;

class DocumentParserService
{
    /**
     * Parse a file and return HTML content.
     *
     * @return array{title: string, content: string}
     */
    public function parse(string $path, string $originalName): array
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        $content = match ($extension) {
            'pdf' => $this->parsePdf($path),
            'doc', 'docx' => $this->parseWord($path),
            'xls', 'xlsx' => $this->parseSpreadsheet($path),
            'csv' => $this->parseCsv($path),
            'txt' => $this->parseTxt($path),
            'json' => $this->parseJson($path),
            default => throw new \RuntimeException("Unsupported file format: .{$extension}"),
        };

        $title = pathinfo($originalName, PATHINFO_FILENAME);

        return [
            'title' => $title,
            'content' => $content,
        ];
    }

    private function parsePdf(string $path): string
    {
        $parser = new PdfParser;
        $pdf = $parser->parseFile($path);
        $text = $pdf->getText();

        return $this->textToHtml($text);
    }

    private function parseWord(string $path): string
    {
        $phpWord = WordIOFactory::load($path);
        $html = '';

        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if (method_exists($element, 'getText')) {
                    $text = $element->getText();
                    if (is_string($text) && trim($text) !== '') {
                        $html .= '<p>'.e($text).'</p>';
                    }
                } elseif ($element instanceof \PhpOffice\PhpWord\Element\Table) {
                    $html .= $this->parseWordTable($element);
                }
            }
        }

        return $html ?: '<p>No content could be extracted.</p>';
    }

    private function parseWordTable(\PhpOffice\PhpWord\Element\Table $table): string
    {
        $html = '<table><tbody>';

        foreach ($table->getRows() as $row) {
            $html .= '<tr>';
            foreach ($row->getCells() as $cell) {
                $cellText = '';
                foreach ($cell->getElements() as $element) {
                    if (method_exists($element, 'getText')) {
                        $text = $element->getText();
                        if (is_string($text)) {
                            $cellText .= e($text).' ';
                        }
                    }
                }
                $html .= '<td>'.trim($cellText).'</td>';
            }
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';

        return $html;
    }

    private function parseSpreadsheet(string $path): string
    {
        $spreadsheet = SpreadsheetIOFactory::load($path);
        $html = '';

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $sheetTitle = $sheet->getTitle();
            $html .= '<h2>'.e($sheetTitle).'</h2>';
            $html .= '<table><tbody>';

            foreach ($sheet->toArray() as $rowIndex => $row) {
                $tag = $rowIndex === 0 ? 'th' : 'td';
                $html .= '<tr>';
                foreach ($row as $cell) {
                    $html .= "<{$tag}>".e((string) ($cell ?? ''))."</{$tag}>";
                }
                $html .= '</tr>';
            }

            $html .= '</tbody></table>';
        }

        return $html;
    }

    private function parseCsv(string $path): string
    {
        $content = file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException('Unable to read CSV file.');
        }

        // Remove BOM
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new \RuntimeException('Unable to open CSV file.');
        }

        $lines = [];
        while (($row = fgetcsv($handle)) !== false) {
            $lines[] = $row;
        }
        fclose($handle);

        if (empty($lines)) {
            return '<p>Empty CSV file.</p>';
        }

        $html = '<table><tbody>';
        foreach ($lines as $rowIndex => $row) {
            $tag = $rowIndex === 0 ? 'th' : 'td';
            $html .= '<tr>';
            foreach ($row as $cell) {
                $html .= "<{$tag}>".e($cell)."</{$tag}>";
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';

        return $html;
    }

    private function parseTxt(string $path): string
    {
        $content = file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException('Unable to read text file.');
        }

        return $this->textToHtml($content);
    }

    private function parseJson(string $path): string
    {
        $content = file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException('Unable to read JSON file.');
        }

        $data = json_decode($content, true);
        if (! is_array($data)) {
            return $this->textToHtml($content);
        }

        return '<pre><code>'.e(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)).'</code></pre>';
    }

    private function textToHtml(string $text): string
    {
        $lines = preg_split('/\r?\n/', $text);
        $html = '';
        $currentParagraph = '';

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                if ($currentParagraph !== '') {
                    $html .= '<p>'.e($currentParagraph).'</p>';
                    $currentParagraph = '';
                }
            } else {
                $currentParagraph .= ($currentParagraph !== '' ? ' ' : '').$trimmed;
            }
        }

        if ($currentParagraph !== '') {
            $html .= '<p>'.e($currentParagraph).'</p>';
        }

        return $html ?: '<p>No content.</p>';
    }
}
