<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;

class ChartRenderer
{
    private const WIDTH = 560;

    private const HEIGHT = 300;

    private const PAD_LEFT = 40;

    private const PAD_RIGHT = 44;

    private const PAD_TOP = 16;

    private const PAD_BOTTOM = 54;

    private const TEMP_MAX = 60;

    /**
     * Render a humidity + temperature line chart as PNG binary, matching the
     * dashboard's Analytics chart as closely as a static image reasonably can:
     * dual axis (humidity 0-100% left, temperature 0-60°C right), filled
     * areas under each line, anti-aliased strokes, dashed warn/critical
     * humidity threshold lines, and a small legend. Used to embed a real
     * chart image (as a base64 data URI) in emailed reports.
     *
     * @param  Collection<int, array{time: Carbon, humidity: ?float, temperature: ?float}>  $points
     *                                                                                               Expected to already be aggregated (e.g. hourly averages) —
     *                                                                                               plotting raw per-minute readings makes for a much noisier
     *                                                                                               line than the dashboard's hourly-averaged chart.
     */
    public function humidityTemperatureChart(Collection $points, ?float $warnHumidity, ?float $critHumidity): string
    {
        $im = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imagealphablending($im, true);

        $white = imagecolorallocate($im, 255, 255, 255);
        $grid = imagecolorallocate($im, 226, 232, 240);
        $textColor = imagecolorallocate($im, 100, 116, 139);
        $orange = imagecolorallocate($im, 194, 65, 12);
        $orangeFill = imagecolorallocatealpha($im, 255, 122, 41, 108);
        $slate = imagecolorallocate($im, 138, 149, 142);
        $slateFill = imagecolorallocatealpha($im, 138, 149, 142, 116);
        $amber = imagecolorallocate($im, 217, 119, 6);
        $red = imagecolorallocate($im, 220, 38, 38);

        imagefill($im, 0, 0, $white);

        $plotLeft = self::PAD_LEFT;
        $plotRight = self::WIDTH - self::PAD_RIGHT;
        $plotTop = self::PAD_TOP;
        $plotBottom = self::HEIGHT - self::PAD_BOTTOM;
        $plotWidth = $plotRight - $plotLeft;
        $plotHeight = $plotBottom - $plotTop;

        // Humidity gridlines/labels (left axis, 0-100%)
        for ($pct = 0; $pct <= 100; $pct += 25) {
            $y = (int) round($plotBottom - ($pct / 100) * $plotHeight);
            imageline($im, $plotLeft, $y, $plotRight, $y, $grid);
            $this->text($im, 2, $y - 7, $pct.'%', $textColor);
        }
        // Temperature labels (right axis, 0-60°C)
        for ($deg = 0; $deg <= self::TEMP_MAX; $deg += 15) {
            $y = (int) round($plotBottom - ($deg / self::TEMP_MAX) * $plotHeight);
            $this->text($im, $plotRight + 4, $y - 7, $deg.'°', $textColor);
        }

        $legendY = self::HEIGHT - 16;
        imagefilledellipse($im, $plotLeft + 4, $legendY, 8, 8, $orange);
        $this->text($im, $plotLeft + 12, $legendY - 7, 'Humidity (%)', $textColor);
        imagefilledellipse($im, $plotLeft + 130, $legendY, 8, 8, $slate);
        $this->text($im, $plotLeft + 138, $legendY - 7, 'Temperature (°C)', $textColor);

        if ($points->isEmpty()) {
            $this->text($im, (int) (self::WIDTH / 2) - 70, (int) (self::HEIGHT / 2), 'No data in this range', $textColor);
        } else {
            $minTime = $points->first()['time']->timestamp;
            $maxTime = max($points->last()['time']->timestamp, $minTime + 1);

            $xFor = fn (int $ts): int => (int) round($plotLeft + (($ts - $minTime) / ($maxTime - $minTime)) * $plotWidth);
            $yForHumidity = fn (float $v): int => (int) round($plotBottom - (min(100, max(0, $v)) / 100) * $plotHeight);
            $yForTemp = fn (float $v): int => (int) round($plotBottom - (min(self::TEMP_MAX, max(0, $v)) / self::TEMP_MAX) * $plotHeight);

            // Dashed threshold lines (drawn under the data lines)
            if ($warnHumidity !== null) {
                $y = $yForHumidity($warnHumidity);
                imagesetstyle($im, [$amber, $amber, $amber, IMG_COLOR_TRANSPARENT, IMG_COLOR_TRANSPARENT]);
                imageline($im, $plotLeft, $y, $plotRight, $y, IMG_COLOR_STYLED);
            }
            if ($critHumidity !== null) {
                $y = $yForHumidity($critHumidity);
                imagesetstyle($im, [$red, $red, $red, IMG_COLOR_TRANSPARENT, IMG_COLOR_TRANSPARENT]);
                imageline($im, $plotLeft, $y, $plotRight, $y, IMG_COLOR_STYLED);
            }

            $this->drawSeries($im, $points, 'humidity', $xFor, $yForHumidity, $plotBottom, $orange, $orangeFill);
            $this->drawSeries($im, $points, 'temperature', $xFor, $yForTemp, $plotBottom, $slate, $slateFill);

            // X-axis labels: first / middle / last
            foreach ([$points->first(), $points->get(intdiv($points->count(), 2)), $points->last()] as $p) {
                $x = $xFor($p['time']->timestamp);
                $this->text($im, max(0, $x - 15), $plotBottom + 6, $p['time']->format('d M'), $textColor);
            }
        }

        imagerectangle($im, $plotLeft, $plotTop, $plotRight, $plotBottom, $grid);

        ob_start();
        imagepng($im);
        $png = ob_get_clean();
        imagedestroy($im);

        return $png;
    }

    /**
     * Draw one series as a filled area + anti-aliased line. Anti-aliasing in
     * GD only applies cleanly to 1px lines, so the "thick line" look is
     * faked by stroking the same anti-aliased line twice, offset by 1px.
     */
    private function drawSeries(
        \GdImage $im,
        Collection $points,
        string $field,
        \Closure $xFor,
        \Closure $yFor,
        int $plotBottom,
        int $lineColor,
        int $fillColor,
    ): void {
        $coords = $points
            ->filter(fn ($p) => $p[$field] !== null)
            ->map(fn ($p) => [$xFor($p['time']->timestamp), $yFor((float) $p[$field])])
            ->values();

        if ($coords->count() < 2) {
            return;
        }

        // Filled area under the line
        $polygon = [];
        foreach ($coords as [$x, $y]) {
            $polygon[] = $x;
            $polygon[] = $y;
        }
        $polygon[] = $coords->last()[0];
        $polygon[] = $plotBottom;
        $polygon[] = $coords->first()[0];
        $polygon[] = $plotBottom;
        imagefilledpolygon($im, $polygon, $fillColor);

        // Anti-aliased line, stroked twice (offset 1px) to read as ~2px thick
        imageantialias($im, true);
        for ($offset = 0; $offset <= 1; $offset++) {
            $prev = null;
            foreach ($coords as [$x, $y]) {
                if ($prev !== null) {
                    imageline($im, $prev[0], $prev[1] + $offset, $x, $y + $offset, $lineColor);
                }
                $prev = [$x, $y];
            }
        }
        imageantialias($im, false);
    }

    /**
     * imagestring() only understands single-byte Latin-1 text — feeding it
     * a UTF-8 string (e.g. the "°" in "Temperature (°C)") misreads the
     * multi-byte sequence as two separate garbage characters. Convert first.
     */
    private function text(\GdImage $im, int $x, int $y, string $text, int $color): void
    {
        imagestring($im, 2, $x, $y, mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8'), $color);
    }
}
