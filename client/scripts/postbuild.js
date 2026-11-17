import fs from 'fs';
import crypto from 'crypto';
import path from 'path';

const srcPath = path.resolve('dist/index.html');
const destPath = path.resolve('vue.html');

if (fs.existsSync(srcPath)) {
  fs.copyFileSync(srcPath, destPath);
  console.log(`\n🎉 Successfully compiled and copied single-file build to: ${destPath}\n`);
} else {
  console.error('\n❌ Build output index.html not found!\n');
  process.exit(1);
}

// Copiar config.js para a raiz (ao lado de vue.html), permitindo edição sem recompilar
const configSrc = path.resolve('dist/config.js');
const configDest = path.resolve('config.js');
if (fs.existsSync(configSrc)) {
  fs.copyFileSync(configSrc, configDest);
  console.log(`⚙️  Runtime config copied to: ${configDest}`);
}

// ─── PWA ────────────────────────────────────────────────────────────────
// O service worker, o manifest e os ícones precisam ficar AO LADO do vue.html
// (o escopo do SW é a pasta onde ele está). A versão gravada no sw.js muda a
// cada build com conteúdo diferente; é isso que faz o navegador instalar o SW
// novo e o app mostrar o aviso "Nova versão disponível".
const pkg = JSON.parse(fs.readFileSync(path.resolve('package.json'), 'utf8'));
const hashDoBuild = crypto
  .createHash('sha256')
  .update(fs.readFileSync(srcPath))
  .update(fs.existsSync(configSrc) ? fs.readFileSync(configSrc) : '')
  .digest('hex')
  .slice(0, 10);
const versaoSw = `${pkg.version}-${hashDoBuild}`;

const swSrc = path.resolve('dist/sw.js');
if (!fs.existsSync(swSrc)) {
  console.error('\n❌ dist/sw.js não encontrado (deveria vir de public/sw.js)!\n');
  process.exit(1);
}
const swConteudo = fs.readFileSync(swSrc, 'utf8').replace(/__BUILD_VERSION__/g, versaoSw);
fs.writeFileSync(swSrc, swConteudo);
fs.writeFileSync(path.resolve('sw.js'), swConteudo);

fs.copyFileSync(path.resolve('dist/manifest.webmanifest'), path.resolve('manifest.webmanifest'));
fs.rmSync(path.resolve('icons'), { recursive: true, force: true });
fs.cpSync(path.resolve('dist/icons'), path.resolve('icons'), { recursive: true });
console.log(`📱 PWA: sw.js (versão ${versaoSw}), manifest.webmanifest e icons/ copiados para a raiz`);
