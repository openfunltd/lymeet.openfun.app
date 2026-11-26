/* 下載影片片段：純前端，沿用 dataly-v2 的做法（抓 IVOD 的 m3u8 分段，用 ffmpeg.wasm 剪出 mp4）。 */
import { FFmpeg } from 'https://cdn.jsdelivr.net/npm/@ffmpeg/ffmpeg@0.12.15/dist/esm/index.js';
import { toBlobURL } from 'https://cdn.jsdelivr.net/npm/@ffmpeg/util@0.12.2/dist/esm/index.js';

const FFMPEG_CORE_BASE = 'https://cdn.jsdelivr.net/npm/@ffmpeg/core@0.12.10/dist/esm';
let ffmpegPromise = null;

function loadFFmpeg(onStatus) {
    if (!ffmpegPromise) {
        ffmpegPromise = (async () => {
            onStatus('載入剪輯引擎（約 30MB，首次使用較久）…');
            const ffmpeg = new FFmpeg();
            const [coreURL, wasmURL] = await Promise.all([
                toBlobURL(`${FFMPEG_CORE_BASE}/ffmpeg-core.js`, 'text/javascript'),
                toBlobURL(`${FFMPEG_CORE_BASE}/ffmpeg-core.wasm`, 'application/wasm'),
            ]);
            // worker 本體要同源（它用相對路徑 import 其他模組），ffmpeg-core 走 CDN 的 blob URL
            const classWorkerURL = `${window.location.origin}/static/js/ffmpeg/worker.js`;
            await ffmpeg.load({ coreURL, wasmURL, classWorkerURL });
            return ffmpeg;
        })();
    }
    return ffmpegPromise;
}

async function resolveChunklistUrl(masterUrl) {
    const res = await fetch(masterUrl);
    if (!res.ok) throw new Error(`無法讀取播放清單（HTTP ${res.status}）`);
    const text = await res.text();
    const line = text.split('\n').map((l) => l.trim()).find((l) => l && !l.startsWith('#'));
    if (!line) throw new Error('播放清單格式無法解析');
    return new URL(line, masterUrl).toString();
}

async function fetchSegments(chunklistUrl) {
    const res = await fetch(chunklistUrl);
    if (!res.ok) throw new Error(`無法讀取分段清單（HTTP ${res.status}）`);
    const lines = (await res.text()).split('\n');
    const segments = [];
    let duration = null;
    for (const raw of lines) {
        const line = raw.trim();
        if (line.startsWith('#EXTINF:')) {
            duration = parseFloat(line.slice('#EXTINF:'.length).split(',')[0]);
        } else if (line && !line.startsWith('#')) {
            segments.push({ url: new URL(line, chunklistUrl).toString(), duration });
            duration = null;
        }
    }
    let cumulative = 0;
    for (const seg of segments) {
        seg.startOffset = cumulative;
        cumulative += seg.duration;
    }
    return segments;
}

async function concatSegments(segments, onStatus) {
    const buffers = [];
    for (let i = 0; i < segments.length; i++) {
        onStatus(`下載片段 ${i + 1}/${segments.length}…`);
        const res = await fetch(segments[i].url);
        if (!res.ok) throw new Error(`分段下載失敗（HTTP ${res.status}）`);
        buffers.push(new Uint8Array(await res.arrayBuffer()));
    }
    const total = buffers.reduce((n, b) => n + b.length, 0);
    const merged = new Uint8Array(total);
    let offset = 0;
    for (const b of buffers) {
        merged.set(b, offset);
        offset += b.length;
    }
    return merged;
}

export async function clipAndDownload({ masterUrl, start, end, filename, onStatus }) {
    const status = onStatus || (() => {});
    if (!(end > start)) throw new Error('結束時間必須晚於開始時間');
    status('讀取播放清單…');
    const chunklistUrl = await resolveChunklistUrl(masterUrl);
    const segments = await fetchSegments(chunklistUrl);
    const covering = segments.filter((seg) => seg.startOffset < end && seg.startOffset + seg.duration > start);
    if (covering.length === 0) throw new Error('找不到對應時間範圍的片段');
    const merged = await concatSegments(covering, status);
    const ffmpeg = await loadFFmpeg(status);
    const localStart = Math.max(0, start - covering[0].startOffset);
    const duration = end - start;
    status('剪輯中…');
    await ffmpeg.writeFile('input.ts', merged);
    await ffmpeg.exec(['-ss', localStart.toFixed(3), '-i', 'input.ts', '-t', duration.toFixed(3), '-c', 'copy', '-avoid_negative_ts', 'make_zero', '-movflags', '+faststart', 'output.mp4']);
    const data = await ffmpeg.readFile('output.mp4');
    await ffmpeg.deleteFile('input.ts');
    await ffmpeg.deleteFile('output.mp4');
    const blob = new Blob([data.buffer], { type: 'video/mp4' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename || 'clip.mp4';
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 10000);
    status('完成！');
}
