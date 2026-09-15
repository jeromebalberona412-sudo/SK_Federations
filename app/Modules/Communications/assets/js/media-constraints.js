/**
 * Shared getUserMedia constraints for Communications voice/video.
 * Mobile front cameras often digitally crop at high ideals (1280+) — keep
 * modest ideals and reset zoom when the device exposes it.
 */

export function isCoarsePointerMobile() {
    try {
        if (window.matchMedia('(pointer: coarse)').matches) return true;
        if (window.matchMedia('(max-width: 900px)').matches) return true;
    } catch (e) { /* ignore */ }
    return /Android|iPhone|iPad|iPod|Mobile/i.test(navigator.userAgent || '');
}

export function audioConstraints() {
    return {
        echoCancellation: true,
        noiseSuppression: true,
        autoGainControl: true,
        channelCount: 1
    };
}

/**
 * Natural framing for self/group video (avoid tight crop / ultra-wide stretch).
 */
export function videoConstraints() {
    var mobile = isCoarsePointerMobile();

    return {
        facingMode: { ideal: 'user' },
        // Prefer 4:3 on phones (native front-cam), 16:9 on desktop.
        aspectRatio: { ideal: mobile ? 4 / 3 : 16 / 9 },
        width: mobile
            ? { ideal: 640, min: 320, max: 960 }
            : { ideal: 960, min: 480, max: 1280 },
        height: mobile
            ? { ideal: 480, min: 240, max: 720 }
            : { ideal: 540, min: 270, max: 720 },
        frameRate: { ideal: mobile ? 24 : 30, max: 30 }
    };
}

export function mediaConstraints(withVideo) {
    return {
        audio: audioConstraints(),
        video: withVideo ? videoConstraints() : false
    };
}

export function videoOnlyConstraints() {
    return {
        audio: false,
        video: videoConstraints()
    };
}

/**
 * If the track supports zoom, pin it to the minimum (usually 1x) so mobile
 * does not start in a cropped / zoomed mode.
 */
export async function applyNaturalCameraFraming(track) {
    if (!track || typeof track.getCapabilities !== 'function' || typeof track.applyConstraints !== 'function') {
        return;
    }

    try {
        var caps = track.getCapabilities() || {};
        var next = {};

        if (caps.facingMode && caps.facingMode.indexOf('user') !== -1) {
            next.facingMode = 'user';
        }

        if (caps.zoom) {
            var minZoom = typeof caps.zoom.min === 'number' ? caps.zoom.min : 1;
            next.advanced = [{ zoom: minZoom }];
        }

        if (caps.width && caps.height) {
            // Soft preference only — do not force exact size (breaks some devices).
            var mobile = isCoarsePointerMobile();
            next.width = { ideal: mobile ? 640 : 960 };
            next.height = { ideal: mobile ? 480 : 540 };
        }

        if (Object.keys(next).length) {
            await track.applyConstraints(next);
        }
    } catch (e) {
        // Capabilities/constraints vary widely; ignore unsupported apply.
    }
}

export async function prepareLocalStream(stream) {
    if (!stream || typeof stream.getVideoTracks !== 'function') return stream;
    var tracks = stream.getVideoTracks();
    for (var i = 0; i < tracks.length; i += 1) {
        await applyNaturalCameraFraming(tracks[i]);
    }
    return stream;
}
