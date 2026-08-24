import fs from 'fs';
import { createRequire } from 'module';

const require = createRequire(import.meta.url);
const XLSX = require('./xlsx.full.min.cjs');

const headers = [
    'First Name', 'Middle Name', 'Last Name', 'Suffix', 'Sex', 'Birthdate', 'Age', 'Contact Number',
    'Position', 'Region', 'Province', 'Municipality', 'Barangay', 'Term Start Date', 'Term End Date', 'Email Address',
];

const outDir = new URL('../app/Modules/Accounts/assets/templates/', import.meta.url);
fs.mkdirSync(outDir.pathname.replace(/^\//, ''), { recursive: true });

for (const [name] of [['sk-officials-batch-template.xlsx'], ['sk-federation-batch-template.xlsx']]) {
    const worksheet = XLSX.utils.aoa_to_sheet([headers]);
    const workbook = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(workbook, worksheet, 'Template');
    const path = new URL(name, outDir);
    XLSX.writeFile(workbook, path.pathname.replace(/^\//, ''));
    console.log('Wrote', path.pathname);
}
