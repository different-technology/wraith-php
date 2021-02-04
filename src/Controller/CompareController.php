<?php

namespace WraithPhp\Controller;

use Exception;
use Imagick;
use ImagickPixel;
use WraithPhp\Configuration;

class CompareController implements ControllerInterface
{
    protected string $directoryScreenshots;
    protected string $directoryComparison;
    protected Configuration $config;
    protected array $result = [];

    /**
     * @param Configuration $config
     * @throws Exception
     */
    public function exec(Configuration $config): void
    {
        $this->config = $config;
        $this->directoryScreenshots = $this->config->baseDirectory . '/data/screenshots/' . $this->config->name . '/';
        $this->directoryComparison = $this->config->baseDirectory . '/data/compare/' . $this->config->name . '/' .
            date('Y-m-d_H-i-s') . '/';
        $dirName1 = $this->config->arguments[3];
        $dirName2 = $this->config->arguments[4];
        $this->validateInput();
        echo 'Compare ' . $this->config->name . ' "' . $dirName1 . '" with "' . $dirName2 . '"' . PHP_EOL;

        if (!is_dir($this->directoryComparison)) {
            mkdir($this->directoryComparison, 0777, true);
        }

        $dir1 = $this->directoryScreenshots . $dirName1 . '/';
        $dir2 = $this->directoryScreenshots . $dirName2 . '/';
        $images = array_merge(scandir($dir1), scandir($dir2));
        $images = array_unique($images, SORT_STRING);

        foreach ($images as $imageFileName) {
            if (is_dir($dir1 . $imageFileName) || is_dir($dir2 . $imageFileName)) {
                continue;
            }

            try {
                $this->compareImages($dir1, $dir2, $imageFileName);
            } catch (Exception $e) {
                echo PHP_EOL . 'ERROR: Could not compare images: ' . $imageFileName . PHP_EOL .
                    'Message: ' . $e->getMessage() . PHP_EOL . PHP_EOL;
            }
        }

        file_put_contents($this->directoryComparison . 'result.json', json_encode([
            'dirName1' => $dirName1,
            'dirName2' => $dirName2,
            'result' => $this->result,
        ], JSON_PRETTY_PRINT));
    }

    /**
     * @param string $dir1
     * @param string $dir2
     * @param string $imageFileName
     * @throws Exception
     */
    protected function compareImages(string $dir1, string $dir2, string $imageFileName): void
    {
        if (!is_file($dir1 . $imageFileName)) {
            echo 'WARNING: Image could not be compared - it\'s missing: ' .
                basename($dir1) . '/' . $imageFileName . PHP_EOL;
            $image1 = new Imagick();
            $image1->newImage(1, 1, new ImagickPixel('red'));
        } else {
            $image1 = new Imagick($dir1 . $imageFileName);
        }

        if (!is_file($dir2 . $imageFileName)) {
            echo 'WARNING: Image could not be compared - it\'s missing: ' .
                basename($dir2) . '/' . $imageFileName . PHP_EOL;
            $image2 = new Imagick();
            $image2->newImage(1, 1, new ImagickPixel('red'));
        } else {
            $image2 = new Imagick($dir2 . $imageFileName);
        }

        // Compare the Images
        $result = $image1->compareImages($image2, Imagick::METRIC_MEANSQUAREERROR);
        $result[0]->setImageFormat("png");
        // Output the results
        file_put_contents($this->directoryComparison . $imageFileName, $result[0]);
        $this->result[] = [
            'fileName' => $imageFileName,
            'difference' => $result[1],
        ];
        if ($result[1] > 0) {
            echo 'Detected difference: ' . $imageFileName . PHP_EOL;
        }
    }

    /**
     * @throws Exception
     */
    protected function validateInput(): void
    {
        foreach ([3, 4] as $argKey) {
            if (empty($this->config->arguments[$argKey])) {
                throw new Exception('Argument ' . $argKey . ' not given.');
            }
            $directory = $this->directoryScreenshots . $this->config->arguments[$argKey];
            if (!is_dir($directory)) {
                throw new Exception('The directory does not exist: ' . $directory);
            }
        }
    }
}