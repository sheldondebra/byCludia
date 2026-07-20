<?php
declare(strict_types=1);

/**
 * Product video helpers: upload, convert (ffmpeg → H.264 MP4), and embed detection.
 */

function ffmpeg_binary(): ?string
{
    static $bin = null;
    if ($bin !== null) {
        return $bin === '' ? null : $bin;
    }
    $candidates = [
        '/opt/homebrew/bin/ffmpeg',
        '/usr/local/bin/ffmpeg',
        '/usr/bin/ffmpeg',
        trim((string) @shell_exec('command -v ffmpeg 2>/dev/null')),
    ];
    foreach ($candidates as $c) {
        if ($c !== '' && is_executable($c)) {
            $bin = $c;
            return $bin;
        }
    }
    $bin = '';
    return null;
}

function video_allowed_extensions(): array
{
    return [
        'mp4', 'm4v', 'mov', 'qt', 'webm', 'ogg', 'ogv',
        'avi', 'mkv', 'wmv', 'flv', 'mpeg', 'mpg', '3gp', '3g2',
        'ts', 'm2ts', 'mts', 'asf', 'vob', 'f4v',
    ];
}

/**
 * Detect YouTube / Vimeo / direct file from a URL or path.
 * @return array{type:string,src:string,embed:?string,id:?string}
 */
function parse_product_video(string $raw): array
{
    $raw = trim($raw);
    if ($raw === '') {
        return ['type' => 'none', 'src' => '', 'embed' => null, 'id' => null];
    }

    // YouTube
    if (preg_match('~(?:youtube\.com/(?:watch\?v=|embed/|shorts/|live/)|youtu\.be/)([A-Za-z0-9_-]{6,})~i', $raw, $m)) {
        $id = $m[1];
        return [
            'type' => 'youtube',
            'src' => $raw,
            'embed' => 'https://www.youtube.com/embed/' . $id . '?rel=0&modestbranding=1&playsinline=1',
            'id' => $id,
        ];
    }

    // Vimeo
    if (preg_match('~vimeo\.com/(?:video/)?(\d+)~i', $raw, $m)) {
        $id = $m[1];
        return [
            'type' => 'vimeo',
            'src' => $raw,
            'embed' => 'https://player.vimeo.com/video/' . $id . '?title=0&byline=0&portrait=0',
            'id' => $id,
        ];
    }

    // Absolute URL (direct file or CDN)
    if (preg_match('~^https?://~i', $raw)) {
        return ['type' => 'file', 'src' => $raw, 'embed' => null, 'id' => null];
    }

    // Local relative path
    if (file_exists(ROOT_PATH . '/' . ltrim($raw, '/'))) {
        return ['type' => 'file', 'src' => asset(ltrim($raw, '/')), 'embed' => null, 'id' => null];
    }

    return ['type' => 'none', 'src' => '', 'embed' => null, 'id' => null];
}

/**
 * Convert any readable video file to browser-friendly H.264/AAC MP4.
 * Returns relative path on success, null on failure.
 */
function convert_video_to_mp4(string $absoluteSource, ?string $preferredRel = null): ?string
{
    $ffmpeg = ffmpeg_binary();
    if (!$ffmpeg || !is_file($absoluteSource)) {
        return null;
    }

    $dir = ROOT_PATH . '/assets/videos/products';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    $base = $preferredRel
        ? pathinfo($preferredRel, PATHINFO_FILENAME)
        : ('v' . time() . '-' . bin2hex(random_bytes(4)));
    $rel = 'assets/videos/products/' . $base . '.mp4';
    $dest = ROOT_PATH . '/' . $rel;

    // Avoid overwriting the source if it's already that path
    if (realpath($absoluteSource) === realpath($dest)) {
        $rel = 'assets/videos/products/' . $base . '-web.mp4';
        $dest = ROOT_PATH . '/' . $rel;
    }

    $cmd = sprintf(
        '%s -y -i %s -c:v libx264 -preset fast -crf 23 -pix_fmt yuv420p -c:a aac -b:a 128k -movflags +faststart -vf "scale=trunc(iw/2)*2:trunc(ih/2)*2" %s 2>&1',
        escapeshellarg($ffmpeg),
        escapeshellarg($absoluteSource),
        escapeshellarg($dest)
    );

    $output = [];
    $code = 0;
    @exec($cmd, $output, $code);

    if ($code === 0 && is_file($dest) && filesize($dest) > 0) {
        return $rel;
    }

    @unlink($dest);
    return null;
}

/**
 * Save an uploaded video, convert to MP4 when needed/possible.
 * @return array{ok:bool,path:?string,error:?string,converted:bool}
 */
function admin_process_video_upload(array $file): array
{
    if (empty($file['tmp_name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $map = [
            UPLOAD_ERR_INI_SIZE => 'Video is larger than the server upload limit. Raise upload_max_filesize / post_max_size (e.g. 128M) in PHP.',
            UPLOAD_ERR_FORM_SIZE => 'Video exceeds the form size limit.',
            UPLOAD_ERR_PARTIAL => 'Upload was interrupted. Please try again.',
            UPLOAD_ERR_NO_FILE => 'No video file selected.',
        ];
        $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        return ['ok' => false, 'path' => null, 'error' => $map[$err] ?? 'Video upload failed.', 'converted' => false];
    }

    $origName = (string) ($file['name'] ?? 'video');
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    if ($ext === '' || !in_array($ext, video_allowed_extensions(), true)) {
        // Allow by MIME sniff as a fallback (some phones omit extensions)
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($file['tmp_name']);
        if (!str_starts_with($mime, 'video/')) {
            return ['ok' => false, 'path' => null, 'error' => 'Unsupported file type. Upload a video (MP4, MOV, WebM, AVI, MKV, etc.) or paste a YouTube/Vimeo link.', 'converted' => false];
        }
        $ext = match ($mime) {
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'video/quicktime' => 'mov',
            'video/x-msvideo' => 'avi',
            'video/x-matroska' => 'mkv',
            default => 'bin',
        };
    }

    $dir = ROOT_PATH . '/assets/videos/products';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    $token = 'v' . time() . '-' . bin2hex(random_bytes(4));
    $rawRel = 'assets/videos/products/' . $token . '.' . $ext;
    $rawAbs = ROOT_PATH . '/' . $rawRel;

    if (!@move_uploaded_file($file['tmp_name'], $rawAbs)) {
        return ['ok' => false, 'path' => null, 'error' => 'Could not save the uploaded video on the server.', 'converted' => false];
    }

    // Already MP4 — optionally remux for faststart; keep as-is if convert fails
    if ($ext === 'mp4' || $ext === 'm4v') {
        $converted = convert_video_to_mp4($rawAbs, $token);
        if ($converted && $converted !== $rawRel) {
            @unlink($rawAbs);
            return ['ok' => true, 'path' => $converted, 'error' => null, 'converted' => true];
        }
        return ['ok' => true, 'path' => $rawRel, 'error' => null, 'converted' => false];
    }

    $converted = convert_video_to_mp4($rawAbs, $token);
    if ($converted) {
        @unlink($rawAbs);
        return ['ok' => true, 'path' => $converted, 'error' => null, 'converted' => true];
    }

    // No ffmpeg: keep original so admin can still store the file (may not play in all browsers)
    if (!ffmpeg_binary()) {
        return [
            'ok' => true,
            'path' => $rawRel,
            'error' => 'Saved, but ffmpeg is not installed so the file was not converted to MP4. Install ffmpeg on the server for best browser support.',
            'converted' => false,
        ];
    }

    @unlink($rawAbs);
    return ['ok' => false, 'path' => null, 'error' => 'Could not convert this video. Try exporting as MP4 (H.264) or paste a YouTube/Vimeo link.', 'converted' => false];
}

/**
 * Normalize a pasted link/path for storage.
 */
function normalize_video_input(string $input): string
{
    $input = trim($input);
    if ($input === '') {
        return '';
    }
    // Convert youtu.be / watch URLs to a clean canonical form for storage
    $parsed = parse_product_video($input);
    if ($parsed['type'] === 'youtube' && $parsed['id']) {
        return 'https://www.youtube.com/watch?v=' . $parsed['id'];
    }
    if ($parsed['type'] === 'vimeo' && $parsed['id']) {
        return 'https://vimeo.com/' . $parsed['id'];
    }
    return $input;
}
