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
use BaconQrCode\Renderer\Module\DotsModule;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Fpdf\Fpdf;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use RuntimeException;

/**
 * PDF label generation class.
 */
class Pdf
{

    private const string FILE_NAME = 'labels.pdf';

    private const float FONT_SIZE_DETAIL = 13.0;

    private const float FONT_SIZE_NAME = 10.0;

    private const float LABEL_POSITION_TEXT = 1.0;

    private const float LABEL_POSITION_COOP = 27.0;

    private const float LABEL_POSITION_RATING = 24.1;

    private const float LINE_HEIGHT = 5.0;

    private const int PAGE_COLUMNS = 3;

    private const int PAGE_ROWS = 10;

    private const float PAGE_LABEL_HEIGHT = 25.275;

    private const float PAGE_LABEL_LEFT_MARGIN = 4.5;

    private const float PAGE_LABEL_TOP_MARGIN = 15.5;

    private const float PAGE_LABEL_WIDTH = 70.25;

    private const float QR_CODE_SIZE = 22.0;

    private const string QR_CODE_TYPE = 'gif';

    // Fpdf::Write() is great for mixing font/style/weight, but it doesn't have
    // a right-justify option, so we'll tweak the starting X position based on
    // the characters that are different widths. These values are only good for
    // this one particular font.
    private const array RIGHT_JUSTIFY_TWEAK = [
        '1' => 0.6,
        '4' => -0.3,
    ];

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

    private readonly vfsStreamDirectory $vfs;

    public function __construct()
    {
        $this->pdf = new Fpdf();
        $this->pdf->AddFont('default', '', 'BarlowCondensed-Regular.php', $this->fontDir());
        $this->pdf->AddFont('default', 'B', 'BarlowCondensed-Bold.php', $this->fontDir());
        $this->pdf->SetDisplayMode('real', 'single');
        $this->pdf->SetAutoPageBreak(false);
        $this->pdf->SetMargins(0, 0, 0);
        $this->pdf->AddPage('P', 'Letter');
        $this->vfs = vfsStream::setup();
    }

    public function __destruct()
    {
        if ($this->added > 0) {
            $this->pdf->Output('F', self::FILE_NAME);
        }
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
        $this->pdf->Image($this->qr($game), null, null, self::QR_CODE_SIZE, self::QR_CODE_SIZE, self::QR_CODE_TYPE);

        // Adjust the X position so that the text is right of the QR code.
        $x += self::QR_CODE_SIZE;

        // Adjust the Y position so that the center of the text block is
        // vertically aligned with the center of the QR code.
        $y += self::LABEL_POSITION_TEXT;

        // Name field.
        $this->pdf->SetXY($x, $y);
        $this->pdf->SetFont('default', 'B', self::FONT_SIZE_NAME);
        $this->pdf->Write(self::LINE_HEIGHT, $game->name);
        $this->pdf->Write(self::LINE_HEIGHT, "\n");

        // Numer of players field.
        $this->pdf->SetX($x);
        $this->pdf->SetFont('default', '', self::FONT_SIZE_DETAIL);
        $this->pdf->Write(self::LINE_HEIGHT, 'Players: ');
        $this->pdf->SetFont('default', 'B', self::FONT_SIZE_DETAIL);
        $this->pdf->Write(self::LINE_HEIGHT, $game->players(true));
        $this->pdf->Write(self::LINE_HEIGHT, "\n");

        // Weight field.
        $this->pdf->SetX($x);
        $this->pdf->SetFont('default', '', self::FONT_SIZE_DETAIL);
        $this->pdf->Write(self::LINE_HEIGHT, 'Weight: ');
        $this->pdf->SetFont('default', 'B', self::FONT_SIZE_DETAIL);
        $this->pdf->SetTextColor(...self::WEIGHT_COLORS[intval($game->weight)]);
        $this->pdf->Write(self::LINE_HEIGHT, sprintf('%0.1f', $game->weight));
        $this->pdf->SetTextColor(...self::TEXT_COLOR);

        // Rating field.
        $rating = sprintf('%0.1f', $game->geekRating);
        $this->pdf->SetX($x + self::LABEL_POSITION_RATING + $this->rightJustifyTweak($rating));
        $this->pdf->SetFont('default', '', self::FONT_SIZE_DETAIL);
        $this->pdf->Write(self::LINE_HEIGHT, ' Rating: ');
        $this->pdf->SetFont('default', 'B', self::FONT_SIZE_DETAIL);
        $this->pdf->Write(self::LINE_HEIGHT, $rating);
        $this->pdf->Write(self::LINE_HEIGHT, "\n");

        // Time field.
        $this->pdf->SetX($x);
        $this->pdf->SetFont('default', '', self::FONT_SIZE_DETAIL);
        $this->pdf->Write(self::LINE_HEIGHT, 'Time: ');
        $this->pdf->SetFont('default', 'B', self::FONT_SIZE_DETAIL);
        $this->pdf->Write(self::LINE_HEIGHT, $game->playTime);

        // Co-Op field.
        $this->pdf->SetX($x + self::LABEL_POSITION_COOP);
        $this->pdf->SetFont('default', '', self::FONT_SIZE_DETAIL);
        $this->pdf->Write(self::LINE_HEIGHT, ' Co-Op: ');
        $this->pdf->SetFont('default', 'B', self::FONT_SIZE_DETAIL);
        $this->pdf->Write(self::LINE_HEIGHT, (bool) $game->cooperative === true ? 'Y' : 'N');

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
        $games = (new Db())->getGames($params);
        if (empty($games) === true) {
            throw new RuntimeException('Pattern matched no titles, nothing generated.');
        }
        foreach ($games as $count => $game) {
            $this->add($game);
            printf("%3d %s\n", $count + 1, $game->name);
        }
        return $this;
    }

    protected function qr(Game $game): string
    {
        $size = intval(self::QR_CODE_SIZE * 300 / 25.4);
        $name = sprintf('%s/%d.%s', $this->vfs->url(), $game->id, self::QR_CODE_TYPE);
        $renderer = new ImageRenderer(
            new RendererStyle($size, 0, new DotsModule(0.95)),
            new ImagickImageBackEnd(self::QR_CODE_TYPE)
        );
        (new Writer($renderer))->writeFile($game->url, $name);
        return $name;
    }

    protected function rightJustifyTweak(string $string): float
    {
        $tweak = 0;
        foreach (self::RIGHT_JUSTIFY_TWEAK as $digit => $value) {
            $tweak += substr_count($string, (string) $digit) * $value;
        }
        return $tweak;
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
