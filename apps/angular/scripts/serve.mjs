/**
 * ng serve 启动包装 — 监听端口取仓库根目录 .env 的 ANGULAR_DEV_PORT（默认交给 Angular，4200）
 *
 * @angular/build 的 dev-server 只在 process.env.PORT 有值时覆盖端口（builders/dev-server/options.js），
 * 故这里把 .env 的值映射成 PORT；`--port` 传参仍可用（命令行参数优先于 angular.json，PORT 优先于两者）。
 * 其余参数原样透传，例：npm run dev -- --open
 * 用 process.execPath 起 ng：沿用「本机 Node 版本偏低时用 npx --package=node@22.22.3」的姿势。
 */
import { spawn } from 'node:child_process';
import { existsSync } from 'node:fs';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const appDir = fileURLToPath(new URL('..', import.meta.url));
const envFile = resolve(appDir, '../../.env');
if (existsSync(envFile)) {
  process.loadEnvFile(envFile);
}
if (process.env.ANGULAR_DEV_PORT) {
  process.env.PORT = process.env.ANGULAR_DEV_PORT;
}

const ng = resolve(appDir, 'node_modules/@angular/cli/bin/ng.js');
spawn(process.execPath, [ng, 'serve', ...process.argv.slice(2)], { stdio: 'inherit' }).on('exit', (code) =>
  process.exit(code ?? 0),
);
