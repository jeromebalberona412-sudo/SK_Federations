/**
 * Client-side mirror of App\Services\ProhibitedWordsService.
 * Server validation is authoritative; this is for immediate UX feedback.
 */
(function () {
    const MESSAGE = 'This content contains prohibited language. Please remove swear words and try again.';

    const LEET = {
        '@': 'a',
        '4': 'a',
        'á': 'a',
        'à': 'a',
        'â': 'a',
        'ä': 'a',
        '3': 'e',
        'é': 'e',
        'è': 'e',
        'ê': 'e',
        '1': 'i',
        'í': 'i',
        'ì': 'i',
        '0': 'o',
        'ó': 'o',
        'ò': 'o',
        'ô': 'o',
        '5': 's',
        $: 's',
        '7': 't',
        '+': 't',
    };

    function wordList() {
        const fromConfig = window.CommunityFeedConfig?.prohibitedWords
            || window.CommentPreviewConfig?.prohibitedWords
            || window.__skProhibitedWords
            || [];
        return Array.isArray(fromConfig) ? fromConfig.map(String) : [];
    }

    function normalize(text) {
        let out = String(text || '').toLowerCase();
        out = out.replace(/[@4áàâä3éèê1íì0óòô5$7+]/g, (ch) => LEET[ch] || ch);
        out = out.replace(/[^\p{L}]+/gu, '');
        out = out.replace(/(.)\1+/gu, '$1');
        return out;
    }

    function normalizedWords() {
        const seen = new Map();
        const out = [];
        wordList().forEach((word) => {
            const n = normalize(word);
            if (!n || n.length < 3) return;
            const label = String(word).trim().toLowerCase();
            if (seen.has(n)) {
                const index = seen.get(n);
                const existing = out[index].label;
                if (existing.includes(' ') && !label.includes(' ')) {
                    out[index].label = label;
                }
                return;
            }
            seen.set(n, out.length);
            out.push({
                normalized: n,
                label,
            });
        });
        return out;
    }

    function matches(text) {
        const haystack = normalize(text);
        if (!haystack) return [];

        const hits = normalizedWords().filter((entry) => haystack.includes(entry.normalized));
        if (!hits.length) return [];

        hits.sort((a, b) => b.normalized.length - a.normalized.length);

        const kept = [];
        hits.forEach((hit) => {
            if (kept.some((longer) => longer.normalized.includes(hit.normalized))) {
                return;
            }
            kept.push(hit);
        });

        return [...new Set(kept.map((hit) => hit.label))];
    }

    function contains(text) {
        return matches(text).length > 0;
    }

    function messageFor(text) {
        const found = matches(text);
        if (!found.length) return MESSAGE;
        const quoted = found.map((word) => `"${word}"`);
        return `Prohibited word(s) found: ${quoted.join(', ')}. Please remove or change them and try again.`;
    }

    window.ProhibitedWords = {
        MESSAGE,
        normalize,
        contains,
        matches,
        messageFor,
        assertClean(text) {
            if (!contains(text)) return null;
            return messageFor(text);
        },
    };
})();
