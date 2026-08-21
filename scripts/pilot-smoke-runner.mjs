#!/usr/bin/env node

import { createServer } from "node:net";
import { dirname, join, resolve } from "node:path";
import process from "node:process";
import { fileURLToPath } from "node:url";

import { boundedFailureDetail, runBoundedSync } from "./pilot-smoke-process.mjs";

const repoRoot = resolve(dirname(fileURLToPath(import.meta.url)), "..");
const harnessPath = join(repoRoot, "scripts/pilot-smoke.mjs");
const requestedPort = process.env.MODRIK_PILOT_SMOKE_PORT;
const pilotPort = requestedPort || String(await findAvailableLoopbackPort());
const harnessTimeoutMs = 55 * 60_000;

if (!requestedPort) {
  console.log(`Pilot smoke selected free loopback port ${pilotPort}.`);
}

const execution = runBoundedSync(process.execPath, [harnessPath, ...process.argv.slice(2)], {
  cwd: repoRoot,
  env: {
    ...process.env,
    MODRIK_PILOT_SMOKE_PORT: pilotPort,
  },
  stdio: "inherit",
  timeoutMs: harnessTimeoutMs,
});
const failureDetail = boundedFailureDetail(execution, "Pilot smoke harness");

if (failureDetail) {
  console.error(failureDetail);
  process.exit(execution.timedOut ? 124 : (execution.result.status ?? 1));
}

process.exit(0);

async function findAvailableLoopbackPort() {
  const server = createServer();
  try {
    await new Promise((resolvePromise, rejectPromise) => {
      server.once("error", rejectPromise);
      server.listen({ host: "127.0.0.1", port: 0, exclusive: true }, resolvePromise);
    });

    const address = server.address();
    if (!address || typeof address === "string") {
      throw new Error("Node did not return an IPv4/IPv6 port for the Pilot smoke fixture server.");
    }

    return address.port;
  } finally {
    if (server.listening) {
      await new Promise((resolvePromise, rejectPromise) => {
        server.close((error) => (error ? rejectPromise(error) : resolvePromise()));
      });
    }
  }
}
