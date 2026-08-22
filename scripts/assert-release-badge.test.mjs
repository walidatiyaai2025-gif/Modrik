import assert from 'node:assert/strict';
import test from 'node:test';

import { assertReleaseBadgeHtml } from './assert-release-badge.mjs';

const releaseSha = 'abcdef1234567890abcdef1234567890abcdef12';

test('accepts a badge containing the exact first 12 release SHA characters', () => {
  const html = '<span data-testid="modrik-web-release-badge">Build abcdef123456</span>';

  assert.deepEqual(
    assertReleaseBadgeHtml(html, {
      testId: 'modrik-web-release-badge',
      releaseSha,
      failureCode: 'MODRIK_WEB_RELEASE_MISMATCH',
    }),
    { testId: 'modrik-web-release-badge', shortSha: 'abcdef123456' },
  );
});

test('accepts React server markup that splits visible Build text with comments', () => {
  const html = '<span class="badge" data-testid="modrik-web-release-badge">Build <!-- -->abcdef123456</span>';

  assert.doesNotThrow(() =>
    assertReleaseBadgeHtml(html, {
      testId: 'modrik-web-release-badge',
      releaseSha,
      failureCode: 'MODRIK_WEB_RELEASE_MISMATCH',
    }),
  );
});

test('rejects stale release text with only the stable failure classification and expected build', () => {
  const secretMarker = 'DO_NOT_PRINT_RESPONSE_BODY';
  const html = `<span data-testid="modrik-release-badge">Build 000000000000</span>${secretMarker}`;

  assert.throws(
    () =>
      assertReleaseBadgeHtml(html, {
        testId: 'modrik-release-badge',
        releaseSha,
        failureCode: 'MODRIK_ADMIN_RELEASE_MISMATCH',
      }),
    (error) => {
      assert.match(error.message, /^MODRIK_ADMIN_RELEASE_MISMATCH:/);
      assert.match(error.message, /Build abcdef123456/);
      assert.doesNotMatch(error.message, new RegExp(secretMarker));
      return true;
    },
  );
});

test('rejects a missing badge without echoing page content', () => {
  const secretMarker = 'PRIVATE_PAGE_BODY';

  assert.throws(
    () =>
      assertReleaseBadgeHtml(`<html>${secretMarker}</html>`, {
        testId: 'modrik-release-badge',
        releaseSha,
        failureCode: 'MODRIK_ADMIN_RELEASE_MISMATCH',
      }),
    (error) => {
      assert.match(error.message, /^MODRIK_ADMIN_RELEASE_MISMATCH:/);
      assert.doesNotMatch(error.message, new RegExp(secretMarker));
      return true;
    },
  );
});
