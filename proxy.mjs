// Lightweight reverse proxy for AI-CMS
// Proxies requests from a free port to Laravel on 8000
import { createServer, request } from 'http';

const TARGET_HOST = '127.0.0.1';
const TARGET_PORT = 8000;
const PROXY_PORT = parseInt(process.env.PROXY_PORT || '18790');

const server = createServer((clientReq, clientRes) => {
    const options = {
        hostname: TARGET_HOST,
        port: TARGET_PORT,
        path: clientReq.url,
        method: clientReq.method,
        headers: {
            ...clientReq.headers,
            host: `${TARGET_HOST}:${TARGET_PORT}`,
        },
    };

    const proxy = request(options, (proxyRes) => {
        clientRes.writeHead(proxyRes.statusCode, proxyRes.headers);
        proxyRes.pipe(clientRes, { end: true });
    });

    proxy.on('error', (err) => {
        console.error('Proxy error:', err.message);
        clientRes.writeHead(502);
        clientRes.end('Bad Gateway');
    });

    clientReq.pipe(proxy, { end: true });
});

server.listen(PROXY_PORT, '0.0.0.0', () => {
    console.log(`AI-CMS proxy running on http://0.0.0.0:${PROXY_PORT} → http://${TARGET_HOST}:${TARGET_PORT}`);
});
