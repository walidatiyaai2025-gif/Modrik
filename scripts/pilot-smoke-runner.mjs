#!/usr/bin/env node

import { spawnSync } from "node:child_process";
import { createServer } from "node:net";
import { dirname, join, resolve } from "node:path";
import process from "node:process";
import { fileURLToPath } from "node:url";

const repoRoot = resolve(dirname(fileURLToPath(import.meta.url)), "..");
const harnessPath = join(repoRoot, "scripts/pilot-smoke.mjs");
const requestedPort = process.env.MODRIK_PILOT_SMOKE_PORT;
const pilotPort = requestedPort || String(await findAvailableLoopbackPort());

if (!requestedPort) {
  console.log(`Pilot smoke selected free loopback port ${pilotPort}.`);
}

const result = spawnSync(process.execPath, [harnessPath, ...process.argv.slice(2)], {
  cwd: repoRoot,
  env: {
    ...process.env,
    MODRIK_PILOT_SMOKE_PORT: pilotPort,
  },
  stdio: "inherit",
});

if (result.error) {
  console.error(`Unable to start Pilot smoke harness: ${result.error.message}`);
  process.exit(1);
}

if (result.signal) {
  console.error(`Pilot smoke harness terminated by signal ${result.signal}.`);
  process.exit(1);
}

process.exit(result.status ?? 1);

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
