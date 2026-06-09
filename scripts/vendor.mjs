// Copies the runtime front-end vendor files (jQuery, Fancybox) out of
// node_modules into assets/vendor so the site can be served without a
// node_modules folder in production. Run via `npm run vendor` (bun) or as a
// postinstall hook. Pure Node, no dependencies.
import { mkdirSync, copyFileSync, existsSync } from 'node:fs'
import { dirname, join } from 'node:path'

const root = process.cwd()
const modules = join(root, 'node_modules')
const vendor = join(root, 'assets', 'vendor')

// [ source relative to node_modules, destination relative to assets/vendor ]
const files = [
  ['jquery/dist/jquery.min.js', 'jquery/jquery.min.js'],
  ['@fancyapps/ui/dist/fancybox/fancybox.umd.js', 'fancybox/fancybox.umd.js'],
  ['@fancyapps/ui/dist/fancybox/fancybox.css', 'fancybox/fancybox.css'],
  ['@fancyapps/ui/dist/fancybox/l10n/de_DE.umd.js', 'fancybox/de_DE.umd.js'],
]

let copied = 0
for (const [src, dst] of files) {
  const from = join(modules, src)
  const to = join(vendor, dst)
  if (!existsSync(from)) {
    console.error(`vendor: missing ${from} — run \`npm install\` first`)
    process.exitCode = 1
    continue
  }
  mkdirSync(dirname(to), { recursive: true })
  copyFileSync(from, to)
  console.log(`vendor: ${dst}`)
  copied++
}

console.log(`vendor: ${copied}/${files.length} files copied to assets/vendor`)
