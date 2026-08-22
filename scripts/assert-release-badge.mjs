import fs from 'node:fs';
import { pathToFileURL } from 'node:url';

function escapeRegExp(value) {
  return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function decodeHtmlText(value) {
  const named = {
    amp: '&',
    apos: "'",
    gt: '>',
    lt: '<',
    nbsp: ' ',
    quot: '"',
  };

  return value.replace(/&(?:#(\d+)|#x([0-9a-f]+)|([a-z]+));/gi, (entity, decimal, hex, name) => {
    if (decimal) {
      return String.fromCodePoint(Number.parseInt(decimal, 10));
    }
    if (hex) {
      return String.fromCodePoint(Number.parseInt(hex, 16));
    }
    return named[name.toLowerCase()] ?? entity;
  });
}

export function assertReleaseBadgeHtml(html, { testId, releaseSha, failureCode }) {
  if (typeof html !== 'string') {
    throw new TypeError('html must be a string');
  }
  if (!/^[0-9a-f]{40}$/i.test(releaseSha ?? '')) {
    throw new Error(`${failureCode}: expected a 40-character immutable release SHA.`);
  }
  if (!/^[a-z0-9][a-z0-9_-]*$/i.test(testId ?? '')) {
    throw new Error(`${failureCode}: invalid release badge test id.`);
  }
  if (!/^MODRIK_[A-Z0-9_]+$/.test(failureCode ?? '')) {
    throw new Error('Invalid MODRIK failure classification.');
  }

  const elementPattern = new RegExp(
    `<([a-z][a-z0-9:-]*)\\b[^>]*\\bdata-testid\\s*=\\s*(["'])${escapeRegExp(testId)}\\2[^>]*>([\\s\\S]{0,4096}?)<\\/\\1\\s*>`,
    'i',
  );
  const match = elementPattern.exec(html);
  const shortSha = releaseSha.slice(0, 12);

  if (!match) {
    throw new Error(`${failureCode}: ${testId} badge was not found.`);
  }

  const visibleText = decodeHtmlText(
    match[3]
      .replace(/<!--[\s\S]*?-->/g, '')
      .replace(/<[^>]+>/g, ' '),
  )
    .replace(/\s+/g, ' ')
    .trim();

  if (!visibleText.includes(`Build ${shortSha}`)) {
    throw new Error(`${failureCode}: ${testId} did not render expected Build ${shortSha}.`);
  }

  return { testId, shortSha };
}

function runCli() {
  const [htmlPath, testId, releaseSha, failureCode] = process.argv.slice(2);
  if (!htmlPath || !testId || !releaseSha || !failureCode) {
    console.error('Usage: node scripts/assert-release-badge.mjs <html-file> <test-id> <release-sha> <failure-code>');
    process.exitCode = 2;
    return;
  }

  try {
    const html = fs.readFileSync(htmlPath, 'utf8');
    const result = assertReleaseBadgeHtml(html, { testId, releaseSha, failureCode });
    console.log(`Verified ${result.testId}: Build ${result.shortSha}`);
  } catch (error) {
    console.error(error instanceof Error ? error.message : String(error));
    process.exitCode = 1;
  }
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  runCli();
}
