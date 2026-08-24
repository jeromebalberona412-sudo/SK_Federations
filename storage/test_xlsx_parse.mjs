import fs from 'fs';
import https from 'https';
import { createRequire } from 'module';

const file = process.argv[2];
const libPath = new URL('./xlsx.full.min.cjs', import.meta.url);

async function downloadLib() {
    return new Promise((resolve, reject) => {
        const out = fs.createWriteStream(libPath.pathname.replace(/^\//, ''));
        https.get('https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js', (res) => {
            res.pipe(out);
            out.on('finish', resolve);
        }).on('error', reject);
    });
}

if (!fs.existsSync(libPath.pathname.replace(/^\//, ''))) {
    await downloadLib();
}

const require = createRequire(import.meta.url);
const XLSX = require('./xlsx.full.min.cjs');

const data = new Uint8Array(fs.readFileSync(file));
const workbook = XLSX.read(data, { type: 'array', raw: false, cellDates: true });
const worksheet = workbook.Sheets[workbook.SheetNames[0]];
const allRows = XLSX.utils.sheet_to_json(worksheet, { header: 1, defval: '' });
console.log(JSON.stringify(allRows.slice(0, 4), null, 2));
