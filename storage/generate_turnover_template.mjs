import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import { createRequire } from 'module';

const require = createRequire(import.meta.url);
const XLSX = require('./xlsx.full.min.cjs');
const rootDir = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');

const headers = [
    'First Name',
    'Middle Name (optional)',
    'Last Name',
    'Suffix (None)',
    'Sex',
    'Birthdate (MM/DD/YYYY)',
    'Age',
    'Contact Number',
    'Federation Position',
    'Barangay',
    'Term Start Date (MM/DD/YYYY)',
    'Term End Date (MM/DD/YYYY)',
    'Email Address',
];

const columnWidths = [
    { wch: 14 },
    { wch: 22 },
    { wch: 14 },
    { wch: 14 },
    { wch: 10 },
    { wch: 22 },
    { wch: 8 },
    { wch: 16 },
    { wch: 21.25 },
    { wch: 17.625 },
    { wch: 22 },
    { wch: 32.625 },
    { wch: 35.75 },
];

const worksheet = XLSX.utils.aoa_to_sheet([headers]);
worksheet['!cols'] = columnWidths;
const workbook = XLSX.utils.book_new();
XLSX.utils.book_append_sheet(workbook, worksheet, 'Turnover');

const targets = [
    path.join(rootDir, 'app/Modules/Turnover/assets/templates/turnover-officers-batch-template.xlsx'),
    path.join(rootDir, 'scripts/app/Modules/Turnover/assets/templates/turnover-officers-batch-template.xlsx'),
];

const buffer = XLSX.write(workbook, { bookType: 'xlsx', type: 'buffer' });

for (const target of targets) {
    fs.mkdirSync(path.dirname(target), { recursive: true });
    fs.writeFileSync(target, buffer);
    console.log('Wrote', target);
}
