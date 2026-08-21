import { spawn, spawnSync } from "node:child_process";
import process from "node:process";

export function runBoundedSync(command, args, { timeoutMs, ...options }) {
  validatePositiveInteger(timeoutMs, "timeoutMs");

  const result = spawnSync(command, args, {
    ...options,
    timeout: timeoutMs,
    killSignal: "SIGTERM",
  });

  return {
    result,
    timedOut: result.error?.code === "ETIMEDOUT",
    timeoutMs,
  };
}

export async function runBoundedProcess(
  command,
  args,
  {
    timeoutMs,
    cleanupGraceMs = 2_000,
    cleanupForceMs = 1_000,
    ...options
  },
) {
  validatePositiveInteger(timeoutMs, "timeoutMs");
  validateNonNegativeInteger(cleanupGraceMs, "cleanupGraceMs");
  validateNonNegativeInteger(cleanupForceMs, "cleanupForceMs");

  const ownsProcessGroup = process.platform !== "win32";
  const child = spawn(command, args, {
    ...options,
    detached: ownsProcessGroup,
  });
  const exitPromise = waitForChildExit(child);
  const timeoutSentinel = Symbol("pilot-timeout");
  let timeoutHandle;
  const timeoutPromise = new Promise((resolve) => {
    timeoutHandle = setTimeout(() => resolve(timeoutSentinel), timeoutMs);
  });

  let timedOut = false;
  let cleanupError = null;
  let residualTreeTerminated = false;
  let result;

  const firstOutcome = await Promise.race([exitPromise, timeoutPromise]);
  if (firstOutcome === timeoutSentinel) {
    timedOut = true;
    try {
      residualTreeTerminated = await terminateOwnedProcessTree(child.pid, {
        cleanupGraceMs,
        cleanupForceMs,
      });
    } catch (error) {
      cleanupError = error;
    }

    result = await Promise.race([
      exitPromise,
      sleep(cleanupGraceMs + cleanupForceMs + 1_000).then(() => ({
        status: null,
        signal: null,
        error: new Error("Timed-out child process did not report exit after process-tree termination."),
      })),
    ]);
  } else {
    result = firstOutcome;
    try {
      residualTreeTerminated = await terminateOwnedProcessTree(child.pid, {
        cleanupGraceMs,
        cleanupForceMs,
      });
    } catch (error) {
      cleanupError = error;
    }
  }

  clearTimeout(timeoutHandle);

  return {
    result,
    timedOut,
    timeoutMs,
    cleanupError,
    residualTreeTerminated,
  };
}

export function boundedFailureDetail(execution, label) {
  const { result, timedOut, timeoutMs, cleanupError } = execution;
  if (timedOut) {
    return `${label} exceeded the ${formatDuration(timeoutMs)} timeout and was terminated.`;
  }
  if (cleanupError) {
    return `${label} process-tree cleanup could not complete: ${cleanupError.message}`;
  }
  if (result.error) {
    return `${label} could not complete: ${result.error.message}`;
  }
  if (result.signal) {
    return `${label} terminated by signal ${result.signal}.`;
  }
  if (result.status !== 0) {
    return `${label} exited with code ${result.status ?? 1}.`;
  }
  return null;
}

async function terminateOwnedProcessTree(pid, { cleanupGraceMs, cleanupForceMs }) {
  if (!Number.isInteger(pid) || pid <= 0) return false;

  if (process.platform === "win32") {
    const taskkill = spawnSync("taskkill", ["/PID", String(pid), "/T", "/F"], {
      stdio: "ignore",
      windowsHide: true,
      timeout: Math.max(cleanupGraceMs + cleanupForceMs, 1_000),
    });
    if (taskkill.error && taskkill.error.code !== "ENOENT") throw taskkill.error;
    return taskkill.status === 0;
  }

  if (!processGroupExists(pid)) return false;

  signalProcessGroup(pid, "SIGTERM");
  if (await waitForProcessGroupExit(pid, cleanupGraceMs)) return true;

  signalProcessGroup(pid, "SIGKILL");
  if (await waitForProcessGroupExit(pid, cleanupForceMs)) return true;

  throw new Error(`owned process group ${pid} remained alive after SIGTERM/SIGKILL cleanup`);
}

function signalProcessGroup(pid, signal) {
  try {
    process.kill(-pid, signal);
  } catch (error) {
    if (error?.code !== "ESRCH") throw error;
  }
}

function processGroupExists(pid) {
  try {
    process.kill(-pid, 0);
    return true;
  } catch (error) {
    if (error?.code === "ESRCH") return false;
    if (error?.code === "EPERM") return true;
    throw error;
  }
}

async function waitForProcessGroupExit(pid, timeoutMs) {
  const deadline = Date.now() + timeoutMs;
  do {
    if (!processGroupExists(pid)) return true;
    await sleep(25);
  } while (Date.now() <= deadline);
  return !processGroupExists(pid);
}

function waitForChildExit(child) {
  return new Promise((resolve) => {
    let settled = false;
    const settle = (result) => {
      if (settled) return;
      settled = true;
      resolve(result);
    };

    child.once("error", (error) => settle({ status: null, signal: null, error }));
    child.once("exit", (status, signal) => settle({ status, signal, error: null }));
  });
}

function sleep(milliseconds) {
  return new Promise((resolve) => setTimeout(resolve, milliseconds));
}

function validatePositiveInteger(value, label) {
  if (!Number.isInteger(value) || value <= 0) {
    throw new TypeError(`${label} must be a positive integer; received ${value}`);
  }
}

function validateNonNegativeInteger(value, label) {
  if (!Number.isInteger(value) || value < 0) {
    throw new TypeError(`${label} must be a non-negative integer; received ${value}`);
  }
}

function formatDuration(milliseconds) {
  if (milliseconds % 60_000 === 0) return `${milliseconds / 60_000} minute(s)`;
  if (milliseconds % 1_000 === 0) return `${milliseconds / 1_000} second(s)`;
  return `${milliseconds} ms`;
}
