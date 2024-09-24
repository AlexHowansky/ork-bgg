<?php

/**
 * Ork BGG
 *
 * @package   Ork\BGG
 * @copyright 2019-2024 Alex Howansky (https://github.com/AlexHowansky)
 * @license   https://github.com/AlexHowansky/ork-bgg/blob/master/LICENSE MIT License
 * @link      https://github.com/AlexHowansky/ork-bgg
 */

namespace Ork\Bgg;

use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\ImagickImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Fpdf\Fpdf;
use RuntimeException;

/**
 * PDF label generation class.
 */
class Pdf
{

    private const string FILE_NAME = 'labels.pdf';

    private const float FONT_SIZE_DETAIL = 13.0;

    private const float FONT_SIZE_NAME = 10.0;

    private const float LINE_HEIGHT = 5.0;

    private const int PAGE_COLUMNS = 3;

    private const int PAGE_ROWS = 10;

    private const float PAGE_LABEL_HEIGHT = 25.4;

    private const float PAGE_LABEL_LEFT_MARGIN = 5.0;

    private const float PAGE_LABEL_TOP_MARGIN = 15.0;

    private const float PAGE_LABEL_WIDTH = 70.5;

    private const int QR_CODE_SIZE = 80;

    private const string QR_CODE_TYPE = 'gif';

    private const array TEXT_COLOR = [0, 0, 0];

    private const array WEIGHT_COLORS = [
        1 => [40, 167, 69],
        2 => [0, 123, 255],
        3 => [255, 193, 7],
        4 => [220, 53, 69],
    ];

    private int $added = 0;

    private readonly Fpdf $pdf;

    private int $position = 0;

    private readonly string $qrCodeFile;

    public function __construct()
    {
        $this->pdf = new Fpdf();
        $this->pdf->AddFont('Barlow', '', 'BarlowCondensed-Regular.php', $this->fontDir());
        $this->pdf->AddFont('Barlow', 'B', 'BarlowCondensed-Bold.php', $this->fontDir());
        $this->pdf->SetDisplayMode('real', 'single');
        $this->pdf->SetAutoPageBreak(false);
        $this->pdf->SetMargins(0, 0, 0);
        $this->pdf->AddPage('P', 'Letter');
        $this->qrCodeFile = tempnam(sys_get_temp_dir(), 'ork-bgg.qr.');
    }

    public function __destruct()
    {
        if (file_exists($this->qrCodeFile) === true) {
            unlink($this->qrCodeFile);
        }
        if ($this->added === 0) {
            throw new RuntimeException('Pattern matched no titles, nothing generated.');
        }
        $this->pdf->Output('F', self::FILE_NAME);
    }

    public function add(Game $game): self
    {
        if ($this->position >= self::PAGE_COLUMNS * self::PAGE_ROWS) {
            $this->pdf->AddPage('P', 'Letter');
            $this->position = 0;
        }

        $x = $this->x();
        $y = $this->y();

        $this->pdf->SetTextColor(...self::TEXT_COLOR);

        // QR code.
        $this->pdf->SetXY($x, $y);
        $this->pdf->Image(file: $this->qr($game), type: self::QR_CODE_TYPE);

        // For the rest of this block's output, shift X right by the width of the QR code.
        $x += self::QR_CODE_SIZE / 4 + 1;

        // And shift Y by a small margin.
        ++$y;

        // Name field.
        $this->pdf->SetXY($x, $y);
        $this->pdf->SetFont('Barlow', 'B', self::FONT_SIZE_NAME);
        $this->pdf->Write(self::LINE_HEIGHT, $game->name);
        $this->pdf->Write(self::LINE_HEIGHT, "\n");

        // Numer of players field.
        $this->pdf->SetX($x);
        $this->pdf->SetFont('Barlow', '', self::FONT_SIZE_DETAIL);
        $this->pdf->Write(self::LINE_HEIGHT, 'Players: ');
        $this->pdf->SetFont('Barlow', 'B', self::FONT_SIZE_DETAIL);
        $this->pdf->Write(self::LINE_HEIGHT, $game->players(true));
        $this->pdf->Write(self::LINE_HEIGHT, "\n");

        // Weight field.
        $this->pdf->SetX($x);
        $this->pdf->SetFont('Barlow', '', self::FONT_SIZE_DETAIL);
        $this->pdf->Write(self::LINE_HEIGHT, 'Weight: ');
        $this->pdf->SetFont('Barlow', 'B', self::FONT_SIZE_DETAIL);
        $this->pdf->SetTextColor(...self::WEIGHT_COLORS[intval($game->weight)]);
        $this->pdf->Write(self::LINE_HEIGHT, sprintf('%0.1f', $game->weight));
        $this->pdf->SetTextColor(...self::TEXT_COLOR);

        // Rating field.
        $this->pdf->SetX($x + 25);
        $this->pdf->SetFont('Barlow', '', self::FONT_SIZE_DETAIL);
        $this->pdf->Write(self::LINE_HEIGHT, ' Rating: ');
        $this->pdf->SetFont('Barlow', 'B', self::FONT_SIZE_DETAIL);
        $this->pdf->Write(self::LINE_HEIGHT, sprintf('%0.1f', $game->geekRating));
        $this->pdf->Write(self::LINE_HEIGHT, "\n");

        // Time field.
        $this->pdf->SetX($x);
        $this->pdf->SetFont('Barlow', '', self::FONT_SIZE_DETAIL);
        $this->pdf->Write(self::LINE_HEIGHT, 'Time: ');
        $this->pdf->SetFont('Barlow', 'B', self::FONT_SIZE_DETAIL);
        $this->pdf->Write(self::LINE_HEIGHT, $game->playTime);

        // Co-Op field.
        $this->pdf->SetX($x + 28);
        $this->pdf->SetFont('Barlow', '', self::FONT_SIZE_DETAIL);
        $this->pdf->Write(self::LINE_HEIGHT, ' Co-Op: ');
        $this->pdf->SetFont('Barlow', 'B', self::FONT_SIZE_DETAIL);
        $this->pdf->Write(self::LINE_HEIGHT, $game->cooperative === true ? 'Y' : 'N');

        ++$this->added;
        ++$this->position;

        return $this;
    }

    protected function fontDir(): string
    {
        return realpath(__DIR__ . '/../fonts') ?: throw new RuntimeException('Unable to locate fonts.');
    }

    public function generate(array $params = []): self
    {
        foreach ((new Db())->getGames($params) as $count => $game) {
            $this->add($game);
            printf("%3d %s\n", $count + 1, $game->name);
        }
        return $this;
    }

    protected function qr(Game $game): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(self::QR_CODE_SIZE, 0),
            new ImagickImageBackEnd(self::QR_CODE_TYPE)
        );
        (new Writer($renderer))->writeFile($game->url, $this->qrCodeFile);
        return $this->qrCodeFile;
    }

    public function skip(int $count): self
    {
        $this->position += $count % (self::PAGE_COLUMNS * self::PAGE_ROWS);
        return $this;
    }

    protected function x(): float
    {
        return ($this->position % self::PAGE_COLUMNS) * self::PAGE_LABEL_WIDTH + self::PAGE_LABEL_LEFT_MARGIN;
    }

    protected function y(): float
    {
        return intval($this->position / self::PAGE_COLUMNS) * self::PAGE_LABEL_HEIGHT + self::PAGE_LABEL_TOP_MARGIN;
    }

}
