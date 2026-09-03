/**
 * Local HTTPS reverse proxy for Laravel Communications / WebRTC (camera + mic).
 *
 * Federations default: Laravel :8000 → HTTPS :8440
 *
 * Usage:
 *   npm run serve:lan
 *   or
 *   Terminal 1: php artisan serve --host=0.0.0.0 --port=8000
 *   Terminal 2: node scripts/dev-https-proxy.cjs
 *
 * Then open: https://192.168.x.x:8440  (accept the self-signed certificate once)
 */

const fs = require('fs');
const https = require('https');
const os = require('os');
const path = require('path');
const httpProxy = require('http-proxy');

const TARGET = process.env.LARAVEL_DEV_URL || 'http://127.0.0.1:8000';
const PORT = parseInt(process.env.HTTPS_DEV_PORT || '8440', 10);
const HOST = process.env.HTTPS_DEV_HOST || '0.0.0.0';
const CERT_DIR = path.join(__dirname, '../storage/certs/dev');

function getLanIpv4Addresses() {
    const ips = new Set(['127.0.0.1']);
    const nets = os.networkInterfaces();

    for (const entries of Object.values(nets)) {
        for (const net of entries || []) {
            if (net.family === 'IPv4' && !net.internal) {
                ips.add(net.address);
            }
        }
    }

    return [...ips];
}

function ensureCerts() {
    fs.mkdirSync(CERT_DIR, { recursive: true });

    const keyPath = path.join(CERT_DIR, 'dev-key.pem');
    const certPath = path.join(CERT_DIR, 'dev-cert.pem');

    if (fs.existsSync(keyPath) && fs.existsSync(certPath)) {
        return {
            key: fs.readFileSync(keyPath),
            cert: fs.readFileSync(certPath),
        };
    }

    let selfsigned;

    try {
        selfsigned = require('selfsigned');
    } catch {
        console.error('Missing package "selfsigned". Run: npm install');
        process.exit(1);
    }

    const lanIps = getLanIpv4Addresses();
    const altNames = [
        { type: 2, value: 'localhost' },
        ...lanIps.map((ip) => (ip.includes('.') ? { type: 7, ip } : { type: 2, value: ip })),
    ];

    const attrs = [{ name: 'commonName', value: 'SK OnePortal Dev HTTPS' }];
    const pems = selfsigned.generate(attrs, {
        days: 825,
        keySize: 2048,
        algorithm: 'sha256',
        extensions: [
            { name: 'basicConstraints', cA: true },
            {
                name: 'keyUsage',
                keyCertSign: true,
                digitalSignature: true,
                nonRepudiation: true,
                keyEncipherment: true,
                dataEncipherment: true,
            },
            { name: 'extKeyUsage', serverAuth: true, clientAuth: true },
            { name: 'subjectAltName', altNames },
        ],
    });

    fs.writeFileSync(keyPath, pems.private);
    fs.writeFileSync(certPath, pems.cert);

    console.log('Generated dev TLS certificate for:', lanIps.join(', '), 'localhost');

    return { key: pems.private, cert: pems.cert };
}

function main() {
    const credentials = ensureCerts();
    const proxy = httpProxy.createProxyServer({
        target: TARGET,
        changeOrigin: true,
        ws: true,
        secure: false,
    });

    proxy.on('error', (err, req, res) => {
        console.error('Proxy error:', err.message);
        if (res.writeHead) {
            res.writeHead(502, { 'Content-Type': 'text/plain' });
            res.end('Dev HTTPS proxy could not reach Laravel. Start: php artisan serve --host=0.0.0.0 --port=8000');
        }
    });

    const server = https.createServer(credentials, (req, res) => {
        proxy.web(req, res);
    });

    server.on('upgrade', (req, socket, head) => {
        proxy.ws(req, socket, head);
    });

    server.listen(PORT, HOST, () => {
        const lanIps = getLanIpv4Addresses().filter((ip) => ip !== '127.0.0.1');
        console.log('');
        console.log('SK Federations — Dev HTTPS proxy running');
        console.log('  Proxy target :', TARGET);
        console.log('  Local HTTPS  :', `https://localhost:${PORT}`);
        lanIps.forEach((ip) => {
            console.log('  LAN HTTPS    :', `https://${ip}:${PORT}`);
        });
        console.log('');
        console.log('Use HTTPS URLs above for voice/video calls.');
        console.log('Accept the browser security warning for the self-signed certificate.');
        console.log('');
    });
}

main();
