/**
 * Shared local cache for list pages.
 * Prefers localStorage; falls back to sessionStorage.
 */
(function () {
    const DEFAULT_TTL_MS = 10 * 60 * 1000;
    const MEMORY = new Map();

    function pickStore() {
        try {
            const probe = '__sk_local_cache_probe__';
            localStorage.setItem(probe, '1');
            localStorage.removeItem(probe);
            return localStorage;
        } catch {
            try {
                const probe = '__sk_local_cache_probe__';
                sessionStorage.setItem(probe, '1');
                sessionStorage.removeItem(probe);
                return sessionStorage;
            } catch {
                return null;
            }
        }
    }

    function get(key) {
        if (!key) return null;
        if (MEMORY.has(key)) {
            return MEMORY.get(key);
        }
        const store = pickStore();
        if (!store) return null;
        try {
            const raw = store.getItem(key);
            if (!raw) return null;
            const parsed = JSON.parse(raw);
            if (!parsed || typeof parsed !== 'object' || !('payload' in parsed)) {
                return null;
            }
            MEMORY.set(key, parsed);
            return parsed;
        } catch {
            return null;
        }
    }

    function set(key, payload) {
        if (!key) return false;
        const entry = { savedAt: Date.now(), payload };
        MEMORY.set(key, entry);
        const store = pickStore();
        if (!store) return true;
        try {
            store.setItem(key, JSON.stringify(entry));
            return true;
        } catch {
            return true;
        }
    }

    function isFresh(entry, ttlMs = DEFAULT_TTL_MS) {
        if (!entry || !entry.savedAt) return false;
        return (Date.now() - Number(entry.savedAt)) < Number(ttlMs || DEFAULT_TTL_MS);
    }

    function remove(key) {
        if (!key) return;
        MEMORY.delete(key);
        try {
            pickStore()?.removeItem(key);
        } catch {
            // Ignore.
        }
    }

    function clearByPrefix(prefix) {
        if (!prefix) return;
        Array.from(MEMORY.keys()).forEach((key) => {
            if (key.startsWith(prefix)) {
                MEMORY.delete(key);
            }
        });
        const store = pickStore();
        if (!store) return;
        try {
            const keys = [];
            for (let i = 0; i < store.length; i += 1) {
                const key = store.key(i);
                if (key && key.startsWith(prefix)) {
                    keys.push(key);
                }
            }
            keys.forEach((key) => store.removeItem(key));
        } catch {
            // Ignore.
        }
    }

    window.SkLocalCache = {
        DEFAULT_TTL_MS,
        get,
        set,
        isFresh,
        remove,
        clearByPrefix,
    };
})();
