<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\ImageOptimizer\OptimizerChainFactory;

class MediaOptimizer
{
    private const MAX_IMAGE_WIDTH = 1920;

    private const MAX_IMAGE_HEIGHT = 1080;

    private const IMAGE_QUALITY = 85;

    private const PNG_COMPRESSION = 9;

    private const VIDEO_BITRATE_KB = 1000;

    private const VIDEO_AUDIO_BITRATE_KB = 128;

    private const FFMPEG_TIMEOUT = 3600;

    private const FFMPEG_THREADS = 12;

    public function optimizeImage(string $path): void
    {
        $fullPath = Storage::disk('public')->path($path);

        if (! file_exists($fullPath)) {
            Log::warning("Image file not found for optimization: {$path}");

            return;
        }

        try {
            $this->resizeImage($fullPath);
            $optimizerChain = OptimizerChainFactory::create();
            $optimizerChain->optimize($fullPath);

            Log::info("Image optimized: {$path}");
        } catch (\Exception $e) {
            Log::error("Failed to optimize image {$path}: {$e->getMessage()}");
        }
    }

    public function optimizeVideo(string $path): void
    {
        $fullPath = Storage::disk('public')->path($path);

        if (! file_exists($fullPath)) {
            Log::warning("Video file not found for optimization: {$path}");

            return;
        }

        try {
            $ffmpegConfig = config('services.media_optimizer', []);

            $ffmpeg = \FFMpeg\FFMpeg::create([
                'ffmpeg.binaries' => $ffmpegConfig['ffmpeg_binaries'] ?? 'ffmpeg',
                'ffprobe.binaries' => $ffmpegConfig['ffprobe_binaries'] ?? 'ffprobe',
                'timeout' => $ffmpegConfig['ffmpeg_timeout'] ?? self::FFMPEG_TIMEOUT,
                'ffmpeg.threads' => $ffmpegConfig['ffmpeg_threads'] ?? self::FFMPEG_THREADS,
            ]);

            $video = $ffmpeg->open($fullPath);
            $format = new \FFMpeg\Format\Video\X264;
            $format->setKiloBitrate(self::VIDEO_BITRATE_KB)
                ->setAudioKiloBitrate(self::VIDEO_AUDIO_BITRATE_KB);

            $outputPath = $fullPath.'.tmp.mp4';
            $video->save($format, $outputPath);

            if (file_exists($outputPath)) {
                unlink($fullPath);
                rename($outputPath, $fullPath);
                Log::info("Video optimized: {$path}");
            }
        } catch (\Exception $e) {
            Log::error("Failed to optimize video {$path}: {$e->getMessage()}");
        }
    }

    public function optimizeMedia(array $mediaPaths): void
    {
        foreach ($mediaPaths as $path) {
            if (empty($path)) {
                continue;
            }

            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

            if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                $this->optimizeImage($path);
            } elseif (in_array($extension, ['mp4', 'mov', 'avi', 'webm'])) {
                $this->optimizeVideo($path);
            }
        }
    }

    private function resizeImage(string $path): void
    {
        if (! extension_loaded('gd') && ! extension_loaded('imagick')) {
            return;
        }

        [$width, $height] = getimagesize($path);

        if ($width <= self::MAX_IMAGE_WIDTH && $height <= self::MAX_IMAGE_HEIGHT) {
            return;
        }

        $ratio = min(self::MAX_IMAGE_WIDTH / $width, self::MAX_IMAGE_HEIGHT / $height);
        $newWidth = (int) ($width * $ratio);
        $newHeight = (int) ($height * $ratio);

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $image = match ($extension) {
            'jpg', 'jpeg' => imagecreatefromjpeg($path),
            'png' => imagecreatefrompng($path),
            'gif' => imagecreatefromgif($path),
            'webp' => imagecreatefromwebp($path),
            default => null,
        };

        if (! $image) {
            return;
        }

        $resized = imagecreatetruecolor($newWidth, $newHeight);

        if ($extension === 'png') {
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
        }

        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        match ($extension) {
            'jpg', 'jpeg' => imagejpeg($resized, $path, self::IMAGE_QUALITY),
            'png' => imagepng($resized, $path, self::PNG_COMPRESSION),
            'gif' => imagegif($resized, $path),
            'webp' => imagewebp($resized, $path, self::IMAGE_QUALITY),
            default => null,
        };

        imagedestroy($image);
        imagedestroy($resized);
    }
} 
