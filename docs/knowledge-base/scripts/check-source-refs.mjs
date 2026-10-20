import { existsSync, readFileSync, readdirSync, statSync } from 'node:fs'
import { dirname, join, relative } from 'node:path'
import { fileURLToPath } from 'node:url'

const knowledgeBase = join(dirname(fileURLToPath(import.meta.url)), '..')
const repoRoot = join(knowledgeBase, '..', '..')

const exempt = new Set([
  'index.md',
  'coverage.md',
  'publication-readiness.md',
  'maintenance.md',
  'README.md',
])

function walk(dir, acc = []) {
  for (const name of readdirSync(dir)) {
    if (name === 'node_modules' || name === '.vitepress' || name === 'scripts') {
      continue
    }

    const path = join(dir, name)
    if (statSync(path).isDirectory()) {
      walk(path, acc)
    } else if (name.endsWith('.md')) {
      acc.push(path)
    }
  }

  return acc
}

function stripCode(text) {
  return text
    .replace(/```[\s\S]*?```/g, '')
    .replace(/`[^`\n]*`/g, '')
}

const sourceRe = /<!--\s*source:\s*(\S+)\s*-->/g
let failed = false

for (const file of walk(knowledgeBase)) {
  const rel = relative(knowledgeBase, file)
  const text = stripCode(readFileSync(file, 'utf8'))
  const sources = [...text.matchAll(sourceRe)].map((match) => match[1])

  if (!exempt.has(rel) && sources.length === 0) {
    console.error(`${rel}: missing <!-- source: path --> comment`)
    failed = true
  }

  for (const source of sources) {
    if (!existsSync(join(repoRoot, source))) {
      console.error(`${rel}: source does not exist: ${source}`)
      failed = true
    }
  }
}

if (failed) {
  process.exit(1)
}

console.log('Source references resolve.')
